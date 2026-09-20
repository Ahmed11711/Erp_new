<?php

namespace Tests\Unit;

use App\Services\Accounting\TrialBalanceLevelView;
use PHPUnit\Framework\TestCase;

class TrialBalanceLevelViewTest extends TestCase
{
    public function test_level_four_includes_exact_level_and_shallower_leaves(): void
    {
        $ids = $this->view()->displayAccountIds($this->tree(), 4);

        $this->assertEqualsCanonicalizing([30, 40, 41], $ids);
    }

    public function test_search_at_level_four_finds_parent_of_matching_child(): void
    {
        $ids = $this->view()->displayAccountIds($this->tree(), 4, 'شركة النور');

        $this->assertSame([40], $ids);
    }

    public function test_search_at_level_four_finds_children_of_matching_ancestor(): void
    {
        $ids = $this->view()->displayAccountIds($this->tree(), 4, 'العملاء');

        $this->assertEqualsCanonicalizing([40, 41], $ids);
    }

    public function test_search_at_level_four_keeps_matching_shallow_leaf(): void
    {
        $ids = $this->view()->displayAccountIds($this->tree(), 4, 'الخزينة');

        $this->assertSame([30], $ids);
    }

    public function test_search_at_all_candidate_ids_restricts_type_like_filter(): void
    {
        $ids = $this->view()->displayAccountIds($this->tree(), 4, null, [40, 50]);

        $this->assertSame([40], $ids);
    }

    private function view(): TrialBalanceLevelView
    {
        return new TrialBalanceLevelView();
    }

    /**
     * @return list<array{id:int,parent_id:?int,level:int,name:string,name_en:string,code:string}>
     */
    private function tree(): array
    {
        return [
            ['id' => 1, 'parent_id' => null, 'level' => 1, 'name' => 'الأصول', 'name_en' => 'Assets', 'code' => '1'],
            ['id' => 10, 'parent_id' => 1, 'level' => 2, 'name' => 'الأصول المتداولة', 'name_en' => '', 'code' => '11'],
            ['id' => 20, 'parent_id' => 10, 'level' => 3, 'name' => 'العملاء', 'name_en' => 'Customers', 'code' => '1101'],
            ['id' => 30, 'parent_id' => 10, 'level' => 3, 'name' => 'الخزينة', 'name_en' => 'Cash', 'code' => '1102'],
            ['id' => 40, 'parent_id' => 20, 'level' => 4, 'name' => 'عملاء محليون', 'name_en' => '', 'code' => '11011'],
            ['id' => 41, 'parent_id' => 20, 'level' => 4, 'name' => 'عملاء تصدير', 'name_en' => '', 'code' => '11012'],
            ['id' => 50, 'parent_id' => 40, 'level' => 5, 'name' => 'شركة النور', 'name_en' => '', 'code' => '1101101'],
        ];
    }
}
