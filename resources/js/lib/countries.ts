/** Countries offered at sign-up and in member details: [code, name, dialling code]. Nigeria first; more can be added here. */
export const countries: [string, string, string][] = [
    ['NG', 'Nigeria', '234'],
    ['GH', 'Ghana', '233'],
    ['BJ', 'Benin', '229'],
    ['CM', 'Cameroon', '237'],
    ['KE', 'Kenya', '254'],
    ['ZA', 'South Africa', '27'],
    ['GB', 'United Kingdom', '44'],
    ['US', 'United States', '1'],
];

export const countryName = (code: string) => countries.find(([c]) => c === code)?.[1] ?? code;
