import { describe, expect, it } from 'vitest'
import {
  isTarget,
  isPlainClick,
  crossUrl,
  decide,
  cityCookie,
  namedCityCookie,
  dismissCookie,
  readCookies,
  cityFromAttr,
  isOutside,
} from '@scripts/features/city-popup/logic.js'

const ORIGIN = 'https://dovira.ddev.site'
const UK = [`${ORIGIN}/services/`, `${ORIGIN}/contacts/`, `${ORIGIN}/services/`]
const RU = [`${ORIGIN}/ru/uslugi/`, `${ORIGIN}/ru/kontakty/`, `${ORIGIN}/ru/services/`]

describe('isTarget', () => {
  it.each([
    ['the services list', `${ORIGIN}/services/`],
    ['a service', `${ORIGIN}/services/diagnostics/`],
    ['a sub-service', `${ORIGIN}/services/surgery/sterilization/`],
    ['contacts', `${ORIGIN}/contacts/`],
    ['contacts without the slash', `${ORIGIN}/contacts`],
    ['a relative href, resolved', '/contacts/'],
    ['a target with a query and a fragment', `${ORIGIN}/services/x/?a=1#prices`],
  ])('the uk list: %s is a target', (_, href) => {
    expect(isTarget(href, ORIGIN, UK)).toBe(true)
  })

  it.each([
    ['the Russian services list', `${ORIGIN}/ru/uslugi/`],
    ['a Russian service', `${ORIGIN}/ru/services/diagnostika/`],
    ['Russian contacts', `${ORIGIN}/ru/kontakty/`],
  ])('the ru list: %s is a target', (_, href) => {
    expect(isTarget(href, ORIGIN, RU)).toBe(true)
  })

  it.each([
    ['the blog', `${ORIGIN}/news/`],
    ['another page', `${ORIGIN}/about/`],
    ['a path that only begins alike', `${ORIGIN}/services-old/`],
    ['the same path on another host', 'https://kyiv.dovira.ddev.site/services/'],
    ['a phone link', 'tel:+380577000000'],
    ['a mail link', 'mailto:info@example.com'],
    ['a bare fragment', '#top'],
    ['an invalid href', 'http://'],
  ])('%s is not a target', (_, href) => {
    expect(isTarget(href, ORIGIN, UK)).toBe(false)
  })

  it('a uk target is not one of the ru list', () => {
    expect(isTarget(`${ORIGIN}/contacts/`, ORIGIN, RU)).toBe(false)
  })
})

describe('isPlainClick', () => {
  const plain = { button: 0, ctrlKey: false, metaKey: false, shiftKey: false, altKey: false }

  it('a primary click on a link in the same window is plain', () => {
    expect(isPlainClick(plain, '')).toBe(true)
    expect(isPlainClick(plain, '_self')).toBe(true)
  })

  it.each(['ctrlKey', 'metaKey', 'shiftKey', 'altKey'])('a click with %s is the browser\'s', (key) => {
    expect(isPlainClick({ ...plain, [key]: true }, '')).toBe(false)
  })

  it('the middle button is the browser\'s', () => {
    expect(isPlainClick({ ...plain, button: 1 }, '')).toBe(false)
  })

  it('a link that opens another window is the browser\'s', () => {
    expect(isPlainClick(plain, '_blank')).toBe(false)
  })
})

describe('crossUrl', () => {
  const KYIV = 'https://kyiv.dovira.ddev.site'

  it('keeps the path, the query and the fragment', () => {
    expect(crossUrl(`${ORIGIN}/services/x/?a=1#prices`, KYIV)).toBe(`${KYIV}/services/x/?a=1&city-popup=1#prices`)
  })

  it('adds the marker to a link without a query', () => {
    expect(crossUrl(`${ORIGIN}/services/`, KYIV)).toBe(`${KYIV}/services/?city-popup=1`)
  })

  it('adds the marker once', () => {
    expect(crossUrl(`${ORIGIN}/services/?city-popup=1`, KYIV)).toBe(`${KYIV}/services/?city-popup=1`)
  })

  it('keeps an existing query as written', () => {
    expect(crossUrl(`${ORIGIN}/contacts/?a=1&b=%20x`, KYIV)).toBe(`${KYIV}/contacts/?a=1&b=%20x&city-popup=1`)
  })
})

describe('decide', () => {
  it('a saved kyiv on Kharkiv goes to Kyiv without asking', () => {
    expect(decide({ dovira_city: 'kyiv' }, 'kharkiv')).toBe('go-city')
  })

  it('a saved kharkiv on Kharkiv follows the link', () => {
    expect(decide({ dovira_city: 'kharkiv' }, 'kharkiv')).toBe('go-current')
  })

  it('a dismissal alone follows the link', () => {
    expect(decide({ dovira_city_dismissed: '1' }, 'kharkiv')).toBe('go-current')
  })

  it('a saved city wins over a dismissal', () => {
    expect(decide({ dovira_city: 'kyiv', dovira_city_dismissed: '1' }, 'kharkiv')).toBe('go-city')
  })

  it('nothing saved asks', () => {
    expect(decide({}, 'kharkiv')).toBe('ask')
  })

  it('an unknown saved value asks', () => {
    expect(decide({ dovira_city: 'odesa' }, 'kharkiv')).toBe('ask')
  })
})

describe('cookie strings', () => {
  it('the city cookie lasts the given days, on the shared domain', () => {
    expect(cityCookie('kyiv', 90, 'dovira.ddev.site', true))
      .toBe('dovira_city=kyiv; Max-Age=7776000; Domain=dovira.ddev.site; Path=/; SameSite=Lax; Secure')
    expect(cityCookie('kharkiv', 30, 'dovira.vet', true)).toContain('Max-Age=2592000')
  })

  it('Secure only on https', () => {
    expect(cityCookie('kyiv', 90, 'dovira.ddev.site', false)).not.toContain('Secure')
    expect(dismissCookie('dovira.ddev.site', false)).not.toContain('Secure')
    expect(dismissCookie('dovira.ddev.site', true)).toContain('; Secure')
  })

  it('the dismissal is a session cookie', () => {
    expect(dismissCookie('dovira.ddev.site', true))
      .toBe('dovira_city_dismissed=1; Domain=dovira.ddev.site; Path=/; SameSite=Lax; Secure')
    expect(dismissCookie('dovira.ddev.site', true)).not.toContain('Max-Age')
  })

  it('reads document.cookie', () => {
    expect(readCookies('a=1; dovira_city=kyiv;  b=x=y')).toEqual({ a: '1', dovira_city: 'kyiv', b: 'x=y' })
    expect(readCookies('')).toEqual({})
  })

  it.each([
    ['kharkiv', 'kharkiv'],
    ['kyiv', 'kyiv'],
    ['Kyiv', null],
    ['', null],
    [undefined, null],
    ['kharkiv,kyiv', null],
  ])('cityFromAttr(%j) is %j', (value, city) => {
    expect(cityFromAttr(value)).toBe(city)
  })
})

describe('namedCityCookie', () => {
  it('the switcher\'s kyiv writes the city cookie', () => {
    expect(namedCityCookie('kyiv', 90, 'dovira.ddev.site', true))
      .toBe('dovira_city=kyiv; Max-Age=7776000; Domain=dovira.ddev.site; Path=/; SameSite=Lax; Secure')
  })

  it('the switcher\'s kharkiv on http writes it without Secure', () => {
    expect(namedCityCookie('kharkiv', 90, 'dovira.ddev.site', false))
      .toBe('dovira_city=kharkiv; Max-Age=7776000; Domain=dovira.ddev.site; Path=/; SameSite=Lax')
  })

  it.each([
    ['Kyiv'],
    ['odesa'],
    [''],
    [undefined],
    ['kharkiv, kyiv'],
  ])('%j writes nothing', (value) => {
    expect(namedCityCookie(value, 90, 'dovira.ddev.site', true)).toBeNull()
  })
})

describe('isOutside', () => {
  const rect = { left: 10, right: 110, top: 20, bottom: 220 }

  it('a point inside the box or on its edge is inside', () => {
    expect(isOutside(rect, 50, 100)).toBe(false)
    expect(isOutside(rect, 10, 20)).toBe(false)
    expect(isOutside(rect, 110, 220)).toBe(false)
  })

  it.each([
    ['left', 9, 100],
    ['right', 111, 100],
    ['above', 50, 19],
    ['below', 50, 221],
  ])('a point %s of the box is outside', (_, x, y) => {
    expect(isOutside(rect, x, y)).toBe(true)
  })
})
