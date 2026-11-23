<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_dueextensions;

/**
 * Picks a dim sum for the golden coin from the public dim sum photo catalog.
 * Names and photos come only from that public catalog; nothing is copied into this repository.
 *
 * @package    local_dueextensions
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dimsum {
    /** @var string The public catalog index. */
    public const INDEX = 'https://raw.githubusercontent.com/Ding-Ding-Projects/dim-sum-photos/main/catalog/index.json';
    /** @var string Base of the public photo release assets. */
    public const RELEASES = 'https://github.com/Ding-Ding-Projects/dim-sum-photos/releases/download/';
    /** @var array[] Which published release holds which dish numbers: [first, last, tag]. */
    public const PARTS = [[1, 995, 'catalog-v1'], [996, 1985, 'catalog-v1-part-002'], [1986, 2928, 'catalog-v1-part-003']];

    /**
     * The dishes with a published photo, cached for a day.
     *
     * @return array[]
     */
    public static function dishes(): array {
        global $CFG;
        $cache = \cache::make('local_dueextensions', 'dimsum');
        $cached = $cache->get('dishes');
        // A full list is kept for a day (the cache's own lifetime); an empty one is retried after an hour.
        if (is_array($cached) && isset($cached['dishes'])
                && ($cached['dishes'] || time() - (int) $cached['at'] < HOURSECS)) {
            return $cached['dishes'];
        }
        $dishes = [];
        try {
            require_once($CFG->libdir . '/filelib.php');
            $curl = new \curl(['cache' => false]);
            $body = $curl->get(self::INDEX, [], ['CURLOPT_TIMEOUT' => 5, 'CURLOPT_CONNECTTIMEOUT' => 3]);
            $json = json_decode((string) $body, true);
            foreach (($json['dishes'] ?? []) as $dish) {
                $url = self::photo_url($dish);
                if (!$url || empty($dish['name']['en'])) {
                    continue;
                }
                $dishes[] = [
                    'en' => (string) $dish['name']['en'],
                    'yue' => (string) ($dish['name']['zhHant'] ?? ''),
                    'image' => $url,
                    'alt' => (string) ($dish['image']['alt']['en'] ?? $dish['name']['en']),
                ];
            }
        } catch (\Throwable $e) {
            debugging('Dim sum catalog unavailable: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
        // An empty list is cached too, so an offline site does not retry on every flip.
        $cache->set('dishes', ['at' => time(), 'dishes' => $dishes]);
        return $dishes;
    }

    /**
     * The public release asset URL for a dish, or null when its photo is not published.
     *
     * @param array $dish
     * @return string|null
     */
    public static function photo_url(array $dish): ?string {
        $file = basename((string) ($dish['image']['path'] ?? ''));
        if (!preg_match('/^hk-dish-(\d{4})-[a-z0-9-]+\.png$/', $file, $m)) {
            return null;
        }
        $number = (int) $m[1];
        foreach (self::PARTS as [$first, $last, $tag]) {
            if ($number >= $first && $number <= $last) {
                return self::RELEASES . $tag . '/' . $file;
            }
        }
        return null;
    }

    /**
     * One random dish, or a name-only fallback when the catalog cannot be reached.
     *
     * @return array en, yue, image (may be empty) and alt
     */
    public static function pick(): array {
        $dishes = self::dishes();
        if (!$dishes) {
            return ['en' => 'Classic Har Gow', 'yue' => '蝦餃', 'image' => '', 'alt' => 'Classic Har Gow'];
        }
        return $dishes[random_int(0, count($dishes) - 1)];
    }
}
