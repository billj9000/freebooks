<?php
// +-----------------------------------------------------------------+
// |                          FreeBooks ERP                          |
// +-----------------------------------------------------------------+
// | Copyright(c) 2026 Bill Jones                                    |
// +-----------------------------------------------------------------+
// | This program is free software: you can redistribute it and/or   |
// | modify it under the terms of the GNU General Public License as  |
// | published by the Free Software Foundation, either version 3 of  |
// | the License, or any later version.                              |
// |                                                                 |
// | This program is distributed in the hope that it will be useful, |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of  |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the   |
// | GNU General Public License for more details.                    |
// +-----------------------------------------------------------------+
//  Path: /modules/contacts/classes/postcode_lookup.php
//

interface PostcodeLookupProvider {
    public function getName(): string;
    /** Returns candidates: array of ['address1','address2','city_town','state_province','postal_code'] */
    public function lookup(string $postcode, string $country): array;
}

class FetchifyProvider implements PostcodeLookupProvider {
    private string $apiKey;

    public function __construct(string $apiKey) { $this->apiKey = $apiKey; }

    public function getName(): string { return 'Fetchify'; }

    public function lookup(string $postcode, string $country): array {
        $url = 'https://api.fetchify.com/AE/address/v1/' . urlencode($this->apiKey)
             . '?query=' . urlencode($postcode) . '&country=' . urlencode($country);
        $raw = @file_get_contents($url);
        if ($raw === false) return [];
        $data = json_decode($raw, true);
        if (!isset($data['completions'])) return [];
        $out = [];
        foreach ($data['completions'] as $c) {
            $lines = $c['lines'] ?? [];
            $out[] = [
                'address1'       => $lines[0] ?? '',
                'address2'       => $lines[1] ?? '',
                'city_town'      => $c['town']    ?? ($lines[2] ?? ''),
                'state_province' => $c['county']  ?? '',
                'postal_code'    => $c['postcode'] ?? $postcode,
            ];
        }
        return $out;
    }
}

class VehicleDataProvider implements PostcodeLookupProvider {
    private string $apiKey;

    public function __construct(string $apiKey) { $this->apiKey = $apiKey; }

    public function getName(): string { return 'Vehicle Data'; }

    public function lookup(string $postcode, string $country): array {
        $url = 'https://api.vehicledata.co.uk/v2/api/address/lookup'
             . '?postcode=' . urlencode($postcode) . '&country=' . urlencode($country);
        $opts = ['http' => ['header' => 'Authorization: ' . $this->apiKey . "\r\n"]];
        $raw = @file_get_contents($url, false, stream_context_create($opts));
        if ($raw === false) return [];
        $data = json_decode($raw, true);
        if (!isset($data['addresses'])) return [];
        $out = [];
        foreach ($data['addresses'] as $a) {
            $out[] = [
                'address1'       => $a['line_1']  ?? '',
                'address2'       => $a['line_2']  ?? '',
                'city_town'      => $a['town']     ?? '',
                'state_province' => $a['county']   ?? '',
                'postal_code'    => $a['postcode'] ?? $postcode,
            ];
        }
        return $out;
    }
}

class PostcodeLookup {
    public static function active(): ?PostcodeLookupProvider {
        if (!defined('POSTCODE_LOOKUP_STATUS') || !POSTCODE_LOOKUP_STATUS) return null;
        $key = defined('POSTCODE_LOOKUP_API_KEY') ? POSTCODE_LOOKUP_API_KEY : '';
        if (!$key) return null;
        switch (defined('POSTCODE_LOOKUP_PROVIDER') ? POSTCODE_LOOKUP_PROVIDER : '') {
            case 'fetchify':     return new FetchifyProvider($key);
            case 'vehicle_data': return new VehicleDataProvider($key);
            default:             return null;
        }
    }
}
