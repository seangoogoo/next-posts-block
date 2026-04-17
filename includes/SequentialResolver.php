<?php
declare(strict_types=1);

namespace SequentialPostsBlock;

/**
 * Pure domain logic: computes N posts following (or preceding) a given post
 * in a canonical ordered list, with wrap-around.
 *
 * Zero WordPress dependency — fully unit-testable in isolation.
 */
final class SequentialResolver
{
    /**
     * @param int[]  $allIds     Canonical-ordered list of post IDs.
     * @param int    $currentId  ID of the post currently displayed.
     * @param string $direction  'asc' (posts after) or 'desc' (posts before).
     * @param int    $count      Number of posts to return.
     * @return int[]             Ordered list of resolved post IDs.
     * @throws \InvalidArgumentException if $direction is not 'asc' or 'desc'.
     */
    public function resolve(array $allIds, int $currentId, string $direction, int $count): array
    {
        if (!in_array($direction, ['asc', 'desc'], true)) {
            throw new \InvalidArgumentException(
                sprintf('Direction must be "asc" or "desc", "%s" given.', $direction)
            );
        }

        if ($count <= 0 || empty($allIds)) {
            return [];
        }

        $total = count($allIds);
        $currentIndex = array_search($currentId, $allIds, true);

        // Fallback: current post absent from list (e.g. draft/trash/future).
        if ($currentIndex === false) {
            $take = min($count, $total);
            return $direction === 'asc'
                ? array_slice($allIds, 0, $take)
                : array_reverse(array_slice($allIds, $total - $take, $take));
        }

        $effective = min($count, $total - 1);
        if ($effective === 0) {
            return [];
        }

        $result = [];
        for ($offset = 1; $offset <= $effective; $offset++) {
            $index = $direction === 'asc'
                ? ($currentIndex + $offset) % $total
                : ($currentIndex - $offset + $total) % $total;
            $result[] = $allIds[$index];
        }

        return $result;
    }
}
