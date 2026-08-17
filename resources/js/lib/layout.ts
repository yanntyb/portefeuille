export type PageWidth = 'narrow' | 'wide';

/**
 * Le fil d'Ariane et le contenu de page partagent ces conteneurs : leurs bords gauches doivent
 * coïncider au pixel (cf. layout.test.ts).
 */
const CONTAINERS: Record<PageWidth, string> = {
    narrow: 'mx-auto w-full max-w-[520px]',
    wide: 'mx-auto w-full max-w-6xl',
};

export const pageContainer = (width: PageWidth): string => CONTAINERS[width];
