<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Search;

use Symfony\Component\String\UnicodeString;

/**
 * Normalization shared by indexing and querying: lower-case, accents removed, punctuation as
 * spaces; hyphenated words are also indexed glued ("t-shirt" → "t shirt tshirt"), and query words
 * are lightly stemmed (French/English plurals: "sweats" → "sweat", "chapeaux" → "chapeau").
 */
final class TextNormalizer
{
    public static function normalize(?string $text): string
    {
        if (null === $text || '' === $text) {
            return '';
        }
        $text = strip_tags($text);
        $ascii = (new UnicodeString($text))->ascii()->lower()->toString();

        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $ascii));
    }

    /** Text to store in the index: normalized words + glued variants of compound words. */
    public static function forIndex(string ...$parts): string
    {
        $words = [];
        foreach ($parts as $part) {
            $lower = (new UnicodeString(strip_tags($part)))->ascii()->lower()->toString();
            foreach (preg_split('/\s+/', $lower) ?: [] as $chunk) {
                if (preg_match('/[a-z0-9][\-\'\.\/][a-z0-9]/', $chunk)) {
                    $words[] = (string) preg_replace('/[^a-z0-9]+/', '', $chunk);
                }
            }
            $words[] = self::normalize($part);
        }

        return ' ' . trim(implode(' ', array_unique(array_filter($words)))) . ' ';
    }

    /**
     * Query words (max 6), each with its alternatives (stem + configured synonyms).
     *
     * @param array<string, list<string>> $synonyms
     *
     * @return list<list<string>>
     */
    public static function queryTerms(string $query, array $synonyms = []): array
    {
        $normalizedSynonyms = [];
        foreach ($synonyms as $word => $alternatives) {
            $all = array_map([self::class, 'normalize'], [$word, ...$alternatives]);
            foreach ($all as $term) {
                $normalizedSynonyms[$term] = array_values(array_unique(array_merge($normalizedSynonyms[$term] ?? [], $all)));
            }
        }

        $terms = [];
        foreach (preg_split('/\s+/u', trim($query)) ?: [] as $chunk) {
            // "t-shirt", "porte-clés", "o'neill": one glued term (compound words are also indexed glued).
            $words = preg_match('/\w[\-\'’.]\w/u', $chunk)
                ? [str_replace(' ', '', self::normalize($chunk))]
                : explode(' ', self::normalize($chunk));
            foreach ($words as $word) {
                if ('' === $word || (\strlen($word) < 2 && !ctype_digit($word))) {
                    continue;
                }
                $alternatives = [self::stem($word)];
                foreach ($normalizedSynonyms[$word] ?? $normalizedSynonyms[self::stem($word)] ?? [] as $synonym) {
                    $alternatives[] = self::stem(str_replace(' ', '', $synonym));
                }
                $terms[] = array_values(array_unique($alternatives));
            }
        }
        $terms = array_slice($terms, 0, 6);

        return $terms;
    }

    public static function stem(string $word): string
    {
        if (\strlen($word) <= 3 || ctype_digit($word)) {
            return $word;
        }
        return str_ends_with($word, 's') || str_ends_with($word, 'x') ? substr($word, 0, -1) : $word;
    }
}
