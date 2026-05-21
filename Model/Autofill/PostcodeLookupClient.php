<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Model\Autofill;

use Magento\Framework\HTTP\Client\Curl;
use Psr\Log\LoggerInterface;

/**
 * Server-side proxy for the getAddress.io UK postcode-lookup API.
 *
 * API key NEVER reaches the browser — the admin AJAX endpoint takes a
 * postcode, this client makes the upstream call server-side, and the
 * endpoint returns the matched addresses to the browser.
 *
 * Free tier: 1,000 lookups/month per key. Paid tiers go up to unlimited.
 *
 * Response shape (simplified for our needs):
 *   [
 *     ['line1' => '1 Some Road',    'line2' => '',          'city' => 'Manchester', 'county' => 'Greater Manchester', 'postcode' => 'M1 1AA'],
 *     ['line1' => '2 Some Road',    'line2' => 'Flat A',    'city' => 'Manchester', 'county' => 'Greater Manchester', 'postcode' => 'M1 1AA'],
 *     ...
 *   ]
 *
 * Failure modes are all returned as empty array — the admin form just
 * sees "no suggestions" and falls back to manual entry.
 */
class PostcodeLookupClient
{
    private const API_ENDPOINT = 'https://api.getaddress.io/find/';
    private const TIMEOUT = 8;

    public function __construct(
        private readonly AutofillConfig $config,
        private readonly Curl $curl,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Look up addresses for a UK postcode. Returns array of normalised
     * address structs.
     *
     * @return array<int, array<string, string>>
     */
    public function lookup(string $postcode): array
    {
        $postcode = $this->normalisePostcode($postcode);
        if ($postcode === '' || !$this->config->hasGetAddressApiKey()) {
            return [];
        }
        $apiKey = $this->config->getGetAddressApiKey();
        $url = self::API_ENDPOINT . rawurlencode($postcode) . '?api-key=' . rawurlencode($apiKey) . '&expand=true';

        try {
            $this->curl->setOption(CURLOPT_TIMEOUT, self::TIMEOUT);
            $this->curl->setOption(CURLOPT_RETURNTRANSFER, true);
            $this->curl->get($url);
        } catch (\Throwable $e) {
            $this->logger->warning('ETechFlow_ISP getAddress.io call failed', [
                'postcode' => $postcode, 'exception' => $e->getMessage()
            ]);
            return [];
        }

        $statusCode = (int) $this->curl->getStatus();
        if ($statusCode !== 200) {
            // 404 = postcode not found; 401 = bad API key; 429 = rate-limited
            return [];
        }
        $body = (string) $this->curl->getBody();
        $data = json_decode($body, true);
        if (!is_array($data) || empty($data['addresses'])) {
            return [];
        }

        return $this->normaliseAddresses($data['addresses'], $postcode);
    }

    /**
     * Strip whitespace, uppercase, validate basic UK postcode shape.
     */
    private function normalisePostcode(string $raw): string
    {
        $stripped = strtoupper(preg_replace('/\s+/', '', $raw) ?? '');
        // Loose UK postcode regex — accepts the major formats:
        //   AB1 2CD, AB12 3CD, A1 2BC, A12 3BC, A1B 2CD, AB1C 2DE
        if (!preg_match('/^[A-Z]{1,2}[0-9][A-Z0-9]?[0-9][A-Z]{2}$/', $stripped)) {
            return '';
        }
        return $stripped;
    }

    /**
     * @param array<int, array<string, mixed>> $rawAddresses
     * @return array<int, array<string, string>>
     */
    private function normaliseAddresses(array $rawAddresses, string $postcode): array
    {
        $result = [];
        // Format postcode back to "M1 1AA" form (space before last 3 chars)
        $pretty = $this->formatPostcode($postcode);
        foreach ($rawAddresses as $addr) {
            $line1 = $this->buildLine1($addr);
            $line2 = trim((string) ($addr['line_2'] ?? ''));
            $city  = trim((string) ($addr['town_or_city'] ?? ''));
            $county = trim((string) ($addr['county'] ?? ''));
            if ($line1 === '') {
                continue;
            }
            $result[] = [
                'line1'    => $line1,
                'line2'    => $line2,
                'city'     => $city,
                'county'   => $county,
                'postcode' => $pretty,
                // A human-readable label for the dropdown
                'label'    => trim(
                    implode(', ', array_filter([$line1, $line2, $city, $pretty]))
                ),
            ];
        }
        return $result;
    }

    private function buildLine1(array $addr): string
    {
        // getAddress.io returns the address in line_1 .. line_4 + thoroughfare/sub_building etc.
        // line_1 typically has the building/number + street; we use it directly.
        $line1 = trim((string) ($addr['line_1'] ?? ''));
        if ($line1 !== '') {
            return $line1;
        }
        // Defensive fallback — manually assemble from sub-parts
        $building = trim((string) ($addr['building_number'] ?? ''));
        $street = trim((string) ($addr['thoroughfare'] ?? ''));
        return trim($building . ' ' . $street);
    }

    private function formatPostcode(string $stripped): string
    {
        if (strlen($stripped) < 3) {
            return $stripped;
        }
        return substr($stripped, 0, -3) . ' ' . substr($stripped, -3);
    }
}
