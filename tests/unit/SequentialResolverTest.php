<?php
declare(strict_types=1);

namespace NextPostsBlock\Tests\Unit;

use PHPUnit\Framework\TestCase;
use NextPostsBlock\SequentialResolver;

final class SequentialResolverTest extends TestCase
{
    private SequentialResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new SequentialResolver();
    }

    public function test_asc_mid_list(): void
    {
        $allIds = [10, 20, 30, 40, 50, 60, 70];
        $result = $this->resolver->resolve($allIds, 30, 'asc', 3);
        $this->assertSame([40, 50, 60], $result);
    }

    public function test_asc_wrap_around_end(): void
    {
        $allIds = [10, 20, 30, 40, 50, 60, 70];
        $result = $this->resolver->resolve($allIds, 60, 'asc', 3);
        $this->assertSame([70, 10, 20], $result);
    }

    public function test_asc_last_item_full_wrap(): void
    {
        $allIds = [10, 20, 30, 40, 50, 60, 70];
        $result = $this->resolver->resolve($allIds, 70, 'asc', 3);
        $this->assertSame([10, 20, 30], $result);
    }

    public function test_desc_mid_list(): void
    {
        $allIds = [10, 20, 30, 40, 50, 60, 70];
        $result = $this->resolver->resolve($allIds, 40, 'desc', 3);
        $this->assertSame([30, 20, 10], $result);
    }

    public function test_desc_wrap_around_start(): void
    {
        $allIds = [10, 20, 30, 40, 50, 60, 70];
        $result = $this->resolver->resolve($allIds, 20, 'desc', 3);
        $this->assertSame([10, 70, 60], $result);
    }

    public function test_desc_first_item_full_wrap(): void
    {
        $allIds = [10, 20, 30, 40, 50, 60, 70];
        $result = $this->resolver->resolve($allIds, 10, 'desc', 3);
        $this->assertSame([70, 60, 50], $result);
    }

    public function test_count_1_returns_single_next(): void
    {
        $allIds = [10, 20, 30];
        $result = $this->resolver->resolve($allIds, 20, 'asc', 1);
        $this->assertSame([30], $result);
    }

    public function test_count_exceeds_available_caps_to_total_minus_one(): void
    {
        $allIds = [10, 20, 30, 40]; // 4 items
        $result = $this->resolver->resolve($allIds, 20, 'asc', 10);
        // Expected: 3 items (total - 1), starting after current, with wrap
        $this->assertCount(3, $result);
        $this->assertSame([30, 40, 10], $result);
    }

    public function test_empty_list_returns_empty(): void
    {
        $this->assertSame([], $this->resolver->resolve([], 999, 'asc', 3));
    }

    public function test_single_post_returns_empty(): void
    {
        $this->assertSame([], $this->resolver->resolve([10], 10, 'asc', 3));
    }

    public function test_count_zero_returns_empty(): void
    {
        $this->assertSame([], $this->resolver->resolve([10, 20, 30], 20, 'asc', 0));
    }

    public function test_negative_count_returns_empty(): void
    {
        $this->assertSame([], $this->resolver->resolve([10, 20, 30], 20, 'asc', -5));
    }

    public function test_current_not_in_list_asc_returns_first_n(): void
    {
        $allIds = [10, 20, 30];
        $result = $this->resolver->resolve($allIds, 999, 'asc', 2);
        $this->assertSame([10, 20], $result);
    }

    public function test_current_not_in_list_desc_returns_last_n_reversed(): void
    {
        $allIds = [10, 20, 30];
        $result = $this->resolver->resolve($allIds, 999, 'desc', 2);
        $this->assertSame([30, 20], $result);
    }

    public function test_invalid_direction_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Direction must be "asc" or "desc"');
        $this->resolver->resolve([10, 20, 30], 20, 'xyz', 3);
    }
}
