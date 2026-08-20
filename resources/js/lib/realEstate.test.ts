import { describe, expect, it } from 'vitest';
import { rentMonthStatus } from '@/lib/realEstate';

describe('rentMonthStatus', () => {
    it('labels a full rent', () => {
        expect(rentMonthStatus({ month: '2026-01-01', expected: 500, effective: 500 })).toBe('plein');
    });

    it('labels a partial payment', () => {
        expect(rentMonthStatus({ month: '2026-01-01', expected: 500, effective: 250 })).toBe('partiel');
    });

    it('labels a default', () => {
        expect(rentMonthStatus({ month: '2026-01-01', expected: 500, effective: 0 })).toBe('impayé');
    });

    it('labels vacancy', () => {
        expect(rentMonthStatus({ month: '2026-01-01', expected: 0, effective: 0 })).toBe('vacance');
    });
});
