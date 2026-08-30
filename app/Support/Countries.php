<?php

namespace App\Support;

class Countries
{
    /**
     * ISO 3166-1 alpha-2 code => country name, for the "where you're based"
     * field on a Profile. Not the same as Heritage — a user's country of
     * residence and their cultural heritage are deliberately separate.
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        return [
            'NG' => 'Nigeria', 'GH' => 'Ghana', 'KE' => 'Kenya', 'ET' => 'Ethiopia',
            'EG' => 'Egypt', 'ZA' => 'South Africa', 'SN' => 'Senegal', 'CI' => "Côte d'Ivoire",
            'CD' => 'DR Congo', 'TZ' => 'Tanzania', 'UG' => 'Uganda', 'MA' => 'Morocco',
            'DZ' => 'Algeria', 'ZW' => 'Zimbabwe', 'CM' => 'Cameroon', 'ML' => 'Mali',
            'SO' => 'Somalia', 'RW' => 'Rwanda',
            'CN' => 'China', 'JP' => 'Japan', 'KR' => 'South Korea', 'IN' => 'India',
            'PK' => 'Pakistan', 'BD' => 'Bangladesh', 'PH' => 'Philippines', 'VN' => 'Vietnam',
            'ID' => 'Indonesia', 'TH' => 'Thailand', 'MY' => 'Malaysia', 'LK' => 'Sri Lanka',
            'NP' => 'Nepal', 'MN' => 'Mongolia', 'TW' => 'Taiwan', 'KH' => 'Cambodia',
            'LA' => 'Laos', 'MM' => 'Myanmar',
            'SA' => 'Saudi Arabia', 'AE' => 'United Arab Emirates', 'IR' => 'Iran', 'TR' => 'Türkiye',
            'LB' => 'Lebanon', 'SY' => 'Syria', 'PS' => 'Palestine', 'IL' => 'Israel',
            'IQ' => 'Iraq', 'YE' => 'Yemen', 'JO' => 'Jordan',
            'IE' => 'Ireland', 'GB' => 'United Kingdom', 'FR' => 'France', 'DE' => 'Germany',
            'IT' => 'Italy', 'ES' => 'Spain', 'PT' => 'Portugal', 'PL' => 'Poland',
            'GR' => 'Greece', 'NL' => 'Netherlands', 'SE' => 'Sweden', 'NO' => 'Norway',
            'DK' => 'Denmark', 'FI' => 'Finland', 'UA' => 'Ukraine', 'RU' => 'Russia',
            'RO' => 'Romania', 'HU' => 'Hungary', 'CZ' => 'Czechia', 'RS' => 'Serbia',
            'HR' => 'Croatia', 'AM' => 'Armenia', 'GE' => 'Georgia',
            'US' => 'United States', 'CA' => 'Canada', 'MX' => 'Mexico', 'BR' => 'Brazil',
            'CO' => 'Colombia', 'AR' => 'Argentina', 'PE' => 'Peru', 'CL' => 'Chile',
            'CU' => 'Cuba', 'JM' => 'Jamaica', 'HT' => 'Haiti', 'DO' => 'Dominican Republic',
            'PR' => 'Puerto Rico', 'TT' => 'Trinidad and Tobago',
            'AU' => 'Australia', 'NZ' => 'New Zealand', 'WS' => 'Samoa', 'FJ' => 'Fiji',
            'TO' => 'Tonga', 'PG' => 'Papua New Guinea',
        ];
    }
}
