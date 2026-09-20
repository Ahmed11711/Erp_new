<?php

namespace Tests\Feature;

use App\Models\TreeAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TreeAccountListPerformanceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_index_returns_flat_list_without_nested_children_queries(): void
    {
        $user = User::factory()->create(['department' => 'Admin']);
        $suffix = uniqid();
        [$root, $child, $grandchild] = $this->seedThreeLevelTree($suffix);

        DB::enableQueryLog();
        DB::flushQueryLog();

        $response = $this->actingAs($user, 'api')->getJson('/api/tree_accounts');

        $treeQueries = $this->countTreeAccountQueries();
        DB::disableQueryLog();

        $response->assertOk();
        $data = collect($response->json('data'));
        $this->assertTrue($data->contains('id', $root->id));
        $this->assertTrue($data->contains('id', $child->id));
        $this->assertTrue($data->contains('id', $grandchild->id));

        $rootRow = $data->firstWhere('id', $root->id);
        $this->assertSame([], $rootRow['children'] ?? []);

        $this->assertLessThanOrEqual(3, $treeQueries, 'دليل الحسابات يجب أن يُجلب بدون N+1 على الأبناء');
    }

    public function test_accounting_tree_nests_all_levels_from_one_query(): void
    {
        $user = User::factory()->create(['department' => 'Admin']);
        $suffix = uniqid();
        [$root, $child, $grandchild] = $this->seedThreeLevelTree($suffix);

        DB::enableQueryLog();
        DB::flushQueryLog();

        $response = $this->actingAs($user, 'api')->getJson('/api/accounting/reports/accounting-tree');

        $treeQueries = $this->countTreeAccountQueries();
        DB::disableQueryLog();

        $response->assertOk();
        $payload = $response->json();
        $roots = collect(isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : $payload);
        $rootRow = $roots->firstWhere('id', $root->id);
        $this->assertNotEmpty($rootRow);
        $childRow = collect($rootRow['children'] ?? [])->firstWhere('id', $child->id);
        $this->assertNotEmpty($childRow);
        $grandchildRow = collect($childRow['children'] ?? [])->firstWhere('id', $grandchild->id);
        $this->assertNotEmpty($grandchildRow);

        $this->assertLessThanOrEqual(3, $treeQueries, 'شجرة الحسابات يجب أن تُبنى من استعلام واحد بدون eager-load متعدد المستويات');
    }

    /**
     * @return array{0: TreeAccount, 1: TreeAccount, 2: TreeAccount}
     */
    private function seedThreeLevelTree(string $suffix): array
    {
        $root = TreeAccount::create([
            'code' => 'LP'.$suffix.'1',
            'name' => 'أصول '.$suffix,
            'type' => 'asset',
            'level' => 1,
            'parent_id' => null,
            'balance' => 12,
            'debit_balance' => 12,
            'credit_balance' => 0,
        ]);

        $child = TreeAccount::create([
            'code' => 'LP'.$suffix.'11',
            'name' => 'متداول '.$suffix,
            'type' => 'asset',
            'level' => 2,
            'parent_id' => $root->id,
            'balance' => 8,
            'debit_balance' => 8,
            'credit_balance' => 0,
        ]);

        $grandchild = TreeAccount::create([
            'code' => 'LP'.$suffix.'111',
            'name' => 'عملاء '.$suffix,
            'type' => 'asset',
            'level' => 3,
            'parent_id' => $child->id,
            'balance' => 5,
            'debit_balance' => 5,
            'credit_balance' => 0,
        ]);

        return [$root, $child, $grandchild];
    }

    private function countTreeAccountQueries(): int
    {
        return collect(DB::getQueryLog())
            ->filter(fn (array $query) => str_contains($query['query'], 'tree_accounts'))
            ->count();
    }
}
