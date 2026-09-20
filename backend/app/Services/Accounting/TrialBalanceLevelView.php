<?php

namespace App\Services\Accounting;

/**
 * اختيار حسابات ميزان المراجعة عند القطع على مستوى معيّن.
 *
 * - المستوى N: الحسابات في هذا المستوى (برصيد الفروع).
 * - الحسابات النهائية في مستوى أقل من N: تُعرض حتى لا يُفقد رصيدها (سبب اختلال المستوى الرابع غالباً).
 * - البحث يطابق الحساب أو أي فرع أو أي أب، ثم تُعرض صفوف المستوى المختار.
 */
class TrialBalanceLevelView
{
    /**
     * @param  iterable<int, array<string, mixed>|object>  $accounts
     * @param  list<int>|null  $candidateIds
     * @return list<int>
     */
    public function displayAccountIds(
        iterable $accounts,
        int $level,
        ?string $search = null,
        ?array $candidateIds = null
    ): array {
        $rows = [];
        foreach ($accounts as $account) {
            $id = (int) $this->value($account, 'id');
            if ($id < 1) {
                continue;
            }
            $rows[$id] = [
                'id' => $id,
                'parent_id' => $this->nullableInt($this->value($account, 'parent_id')),
                'level' => (int) $this->value($account, 'level'),
                'name' => (string) ($this->value($account, 'name') ?? ''),
                'name_en' => (string) ($this->value($account, 'name_en') ?? ''),
                'code' => (string) ($this->value($account, 'code') ?? ''),
            ];
        }

        $childrenMap = [];
        $hasChildren = [];
        $parentMap = [];
        foreach ($rows as $id => $row) {
            $parentId = $row['parent_id'];
            $parentMap[$id] = $parentId;
            if ($parentId) {
                $childrenMap[$parentId][] = $id;
                $hasChildren[$parentId] = true;
            }
        }

        $candidateSet = $candidateIds === null ? null : array_fill_keys($candidateIds, true);
        $display = [];
        foreach ($rows as $id => $row) {
            if ($candidateSet !== null && !isset($candidateSet[$id])) {
                continue;
            }
            $isExactLevel = $row['level'] === $level;
            $isShallowLeaf = $row['level'] < $level && empty($hasChildren[$id]);
            if ($isExactLevel || $isShallowLeaf) {
                $display[$id] = true;
            }
        }

        $needle = trim((string) $search);
        if ($needle !== '') {
            $matched = [];
            foreach ($rows as $id => $row) {
                if ($this->matchesSearch($row, $needle)) {
                    $matched[$id] = true;
                }
            }
            foreach ($display as $id => $_) {
                if (!$this->selfDescendantOrAncestorMatches((int) $id, $matched, $childrenMap, $parentMap)) {
                    unset($display[$id]);
                }
            }
        }

        return array_map('intval', array_keys($display));
    }

    /**
     * @param  array{name: string, name_en: string, code: string}  $row
     */
    private function matchesSearch(array $row, string $needle): bool
    {
        $needleLower = mb_strtolower($needle);

        return $this->contains($row['name'], $needleLower)
            || $this->contains($row['name_en'], $needleLower)
            || $this->contains($row['code'], $needleLower);
    }

    private function contains(string $haystack, string $needleLower): bool
    {
        if ($haystack === '' || $needleLower === '') {
            return false;
        }

        return mb_strpos(mb_strtolower($haystack), $needleLower) !== false;
    }

    /**
     * @param  array<int, true>  $matched
     * @param  array<int, list<int>>  $childrenMap
     * @param  array<int, int|null>  $parentMap
     */
    private function selfDescendantOrAncestorMatches(
        int $id,
        array $matched,
        array $childrenMap,
        array $parentMap
    ): bool {
        if (isset($matched[$id])) {
            return true;
        }

        $stack = $childrenMap[$id] ?? [];
        $seen = [$id => true];
        while ($stack) {
            $childId = (int) array_pop($stack);
            if (isset($seen[$childId])) {
                continue;
            }
            $seen[$childId] = true;
            if (isset($matched[$childId])) {
                return true;
            }
            foreach ($childrenMap[$childId] ?? [] as $grandChildId) {
                $stack[] = (int) $grandChildId;
            }
        }

        $current = $parentMap[$id] ?? null;
        $guard = 0;
        while ($current && $guard < 64) {
            $guard++;
            if (isset($matched[$current])) {
                return true;
            }
            if (isset($seen[$current])) {
                break;
            }
            $seen[$current] = true;
            $current = $parentMap[$current] ?? null;
        }

        return false;
    }

    private function value(array|object $account, string $key): mixed
    {
        if (is_array($account)) {
            return $account[$key] ?? null;
        }

        return $account->{$key} ?? null;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $id = (int) $value;

        return $id > 0 ? $id : null;
    }
}
