// The gate's plumbing, not a feature: a module under source/scripts loads
// through the @scripts alias in the node environment.
import { describe, expect, it } from 'vitest'
import { toggleCities } from '@scripts/modules/toggle-cities.js'

describe('vitest setup', () => {
  it('imports a theme module through the @scripts alias', () => {
    expect(typeof toggleCities).toBe('function')
  })
})
