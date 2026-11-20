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


namespace local_materialtools;

/**
 * A strict JSON reader that refuses duplicate keys and nesting deeper than a limit.
 *
 * PHP's json_decode() silently keeps the last of two equal keys, which would let a malformed vocabulary file apply
 * a word the author never meant, so the vocabulary is read with this parser instead.
 *
 * @package    local_materialtools
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class strict_json {
    /** @var string Source text. */
    private string $s;
    /** @var int Read position. */
    private int $i = 0;
    /** @var int Source length. */
    private int $n;
    /** @var int Allowed depth. */
    private int $maxdepth;
    /** @var bool Whether the root value is an object. */
    private bool $objectroot = false;

    /**
     * Constructor.
     *
     * @param string $source
     * @param int $maxdepth
     */
    public function __construct(string $source, int $maxdepth) {
        $this->s = $source;
        $this->n = strlen($source);
        $this->maxdepth = $maxdepth;
    }

    /**
     * Parse the whole text.
     *
     * @return mixed
     */
    public function parse() {
        $this->ws();
        $this->objectroot = $this->peek() === '{';
        $value = $this->value(1);
        $this->ws();
        if ($this->i !== $this->n) {
            $this->fail('vocaberrorsyntax');
        }
        return $value;
    }

    /**
     * Was the root an object?
     *
     * @return bool
     */
    public function is_object_root(): bool {
        return $this->objectroot;
    }

    /**
     * Stop with a reason.
     *
     * @param string $reason
     */
    private function fail(string $reason): void {
        throw new \moodle_exception($reason, 'local_materialtools');
    }

    /**
     * Next character without moving.
     *
     * @return string
     */
    private function peek(): string {
        return $this->i < $this->n ? $this->s[$this->i] : '';
    }

    /**
     * Skip whitespace.
     */
    private function ws(): void {
        while ($this->i < $this->n && strpos(" \t\r\n", $this->s[$this->i]) !== false) {
            $this->i++;
        }
    }

    /**
     * Any value.
     *
     * @param int $depth
     * @return mixed
     */
    private function value(int $depth) {
        if ($depth > $this->maxdepth) {
            $this->fail('vocaberrordepth');
        }
        $this->ws();
        $c = $this->peek();
        if ($c === '{') {
            return $this->obj($depth);
        }
        if ($c === '[') {
            return $this->arr($depth);
        }
        if ($c === '"') {
            return $this->str();
        }
        foreach (['true' => true, 'false' => false, 'null' => null] as $word => $result) {
            if (substr($this->s, $this->i, strlen($word)) === $word) {
                $this->i += strlen($word);
                return $result;
            }
        }
        if (preg_match('/-?(0|[1-9]\d*)(\.\d+)?([eE][+-]?\d+)?/A', $this->s, $m, 0, $this->i)) {
            $this->i += strlen($m[0]);
            return (isset($m[2]) && $m[2] !== '') || (isset($m[3]) && $m[3] !== '') ? (float) $m[0] : (int) $m[0];
        }
        $this->fail('vocaberrorsyntax');
        return null;
    }

    /**
     * An object.
     *
     * @param int $depth
     * @return array
     */
    private function obj(int $depth): array {
        $this->i++;
        $out = [];
        $this->ws();
        if ($this->peek() === '}') {
            $this->i++;
            return $out;
        }
        while (true) {
            $this->ws();
            if ($this->peek() !== '"') {
                $this->fail('vocaberrorsyntax');
            }
            $key = $this->str();
            if (array_key_exists($key, $out)) {
                $this->fail('vocaberrorduplicate');
            }
            $this->ws();
            if ($this->peek() !== ':') {
                $this->fail('vocaberrorsyntax');
            }
            $this->i++;
            $out[$key] = $this->value($depth + 1);
            $this->ws();
            $c = $this->peek();
            $this->i++;
            if ($c === '}') {
                return $out;
            }
            if ($c !== ',') {
                $this->fail('vocaberrorsyntax');
            }
        }
    }

    /**
     * An array.
     *
     * @param int $depth
     * @return array
     */
    private function arr(int $depth): array {
        $this->i++;
        $out = [];
        $this->ws();
        if ($this->peek() === ']') {
            $this->i++;
            return $out;
        }
        while (true) {
            $out[] = $this->value($depth + 1);
            $this->ws();
            $c = $this->peek();
            $this->i++;
            if ($c === ']') {
                return $out;
            }
            if ($c !== ',') {
                $this->fail('vocaberrorsyntax');
            }
        }
    }

    /**
     * A string, decoded by json_decode once its extent is known.
     *
     * @return string
     */
    private function str(): string {
        if (!preg_match('/"(?:[^"\\\\\x00-\x1F]|\\\\["\\\\\/bfnrt]|\\\\u[0-9a-fA-F]{4})*"/A', $this->s, $m, 0, $this->i)) {
            $this->fail('vocaberrorsyntax');
        }
        $this->i += strlen($m[0]);
        $decoded = json_decode($m[0]);
        if (!is_string($decoded)) {
            $this->fail('vocaberrorsyntax');
        }
        return $decoded;
    }
}
