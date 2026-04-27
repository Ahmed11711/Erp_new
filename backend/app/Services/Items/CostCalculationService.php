<?php

namespace App\Services\Items;

use App\Models\Recipe;
use App\Models\RecipeExtraCost;
use App\Models\RecipeIngredient;

/**
 * Pure-logic service for BOM cost calculations.
 *
 * Deterministic, side-effect-free — safe for both real-time previews and
 * persistence-time assertions.
 *
 * Precision: all intermediate values use bcmath (scale 4) to eliminate
 * floating-point drift; final rounding applied at the boundary.
 */
class CostCalculationService
{
    private const SCALE = 4;

    // ------------------------------------------------------------------
    // Materials cost
    // ------------------------------------------------------------------

    /**
     * Sum of (quantity × unit_cost) for every ingredient.
     *
     * @param  iterable<RecipeIngredient|array{quantity:numeric,unit_cost:numeric}>  $ingredients
     */
    public function materialsCost(iterable $ingredients): string
    {
        $total = '0';

        foreach ($ingredients as $row) {
            $qty  = is_array($row) ? (string) $row['quantity']  : (string) $row->quantity;
            $cost = is_array($row) ? (string) $row['unit_cost'] : (string) $row->unit_cost;

            if ($cost === '' || $cost === null) {
                continue;
            }

            $lineCost = bcmul($qty, $cost, self::SCALE);
            $total    = bcadd($total, $lineCost, self::SCALE);
        }

        return $total;
    }

    // ------------------------------------------------------------------
    // Extra costs
    // ------------------------------------------------------------------

    /**
     * Total of all fixed extra-cost lines.
     *
     * @param  iterable<RecipeExtraCost|array{type:string,value:numeric}>  $extras
     */
    public function totalFixedCosts(iterable $extras): string
    {
        $total = '0';

        foreach ($extras as $row) {
            $type  = is_array($row) ? $row['type']  : $row->type;
            $value = is_array($row) ? (string) $row['value'] : (string) $row->value;

            if ($type === 'fixed') {
                $total = bcadd($total, $value, self::SCALE);
            }
        }

        return $total;
    }

    /**
     * Total of all percentage-based extra-cost lines applied on $materialsCost.
     *
     * @param  iterable<RecipeExtraCost|array{type:string,value:numeric}>  $extras
     */
    public function totalPercentageCosts(iterable $extras, string $materialsCost): string
    {
        $total = '0';

        foreach ($extras as $row) {
            $type  = is_array($row) ? $row['type']  : $row->type;
            $value = is_array($row) ? (string) $row['value'] : (string) $row->value;

            if ($type === 'percentage') {
                $pct    = bcdiv($value, '100', 8);
                $amount = bcmul($materialsCost, $pct, self::SCALE);
                $total  = bcadd($total, $amount, self::SCALE);
            }
        }

        return $total;
    }

    // ------------------------------------------------------------------
    // Final cost
    // ------------------------------------------------------------------

    /**
     * final_cost = materials_cost + sum(fixed) + sum(percentage of materials)
     *
     * @param  iterable  $ingredients   RecipeIngredient rows or arrays
     * @param  iterable  $extraCosts    RecipeExtraCost rows or arrays
     * @return array{materials_cost:string, fixed_costs:string, percentage_costs:string, final_cost:string}
     */
    public function calculateFinalCost(iterable $ingredients, iterable $extraCosts = []): array
    {
        $matCost = $this->materialsCost($ingredients);
        $fixed   = $this->totalFixedCosts($extraCosts);
        $pct     = $this->totalPercentageCosts($extraCosts, $matCost);

        $final = bcadd(bcadd($matCost, $fixed, self::SCALE), $pct, self::SCALE);

        return [
            'materials_cost'   => $matCost,
            'fixed_costs'      => $fixed,
            'percentage_costs' => $pct,
            'final_cost'       => $final,
        ];
    }

    // ------------------------------------------------------------------
    // Margin
    // ------------------------------------------------------------------

    /**
     * margin % = (selling_price - total_cost) / selling_price
     *
     * Returns null when selling_price is zero (division-by-zero guard).
     */
    public function marginPercent(string $sellingPrice, string $totalCost): ?string
    {
        if (bccomp($sellingPrice, '0', self::SCALE) === 0) {
            return null;
        }

        $profit = bcsub($sellingPrice, $totalCost, self::SCALE);
        $margin = bcdiv($profit, $sellingPrice, 6);

        return $margin;
    }

    /**
     * Convenience: full cost breakdown + margin for a Recipe model.
     *
     * @return array{materials_cost:string, fixed_costs:string, percentage_costs:string, final_cost:string, selling_price:string|null, margin_percent:string|null}
     */
    public function breakdownForRecipe(Recipe $recipe, ?string $sellingPrice = null): array
    {
        $recipe->loadMissing(['ingredients', 'extraCosts']);

        $result = $this->calculateFinalCost($recipe->ingredients, $recipe->extraCosts);

        if ($sellingPrice === null) {
            $finishedGood = $recipe->itemsUsingRecipe()
                ->where('warehouse', 'مخزن منتج تام')
                ->first();

            $sellingPrice = $finishedGood
                ? (string) ($finishedGood->sell_total_price ?: $finishedGood->category_price ?: '0')
                : '0';
        }

        $result['selling_price']  = $sellingPrice;
        $result['margin_percent'] = $this->marginPercent($sellingPrice, $result['final_cost']);

        return $result;
    }

    // ------------------------------------------------------------------
    // Validation helpers (Task 5: edge cases)
    // ------------------------------------------------------------------

    /**
     * @return array<int,string>  List of human-readable issues found.
     */
    public function validateIngredients(iterable $ingredients): array
    {
        $errors   = [];
        $seen     = [];

        foreach ($ingredients as $idx => $row) {
            $qty  = is_array($row) ? ($row['quantity']  ?? null) : $row->quantity;
            $cost = is_array($row) ? ($row['unit_cost'] ?? null) : $row->unit_cost;
            $id   = is_array($row) ? ($row['item_id']   ?? null) : $row->item_id;

            if ($qty === null || (float) $qty < 0) {
                $errors[] = "Ingredient #{$idx}: negative quantity ({$qty}).";
            }

            if ($qty !== null && bccomp((string) $qty, '0', 6) === 0) {
                $errors[] = "Ingredient #{$idx}: zero quantity.";
            }

            if ($cost === null || $cost === '') {
                $errors[] = "Ingredient #{$idx}: missing unit cost.";
            } elseif ((float) $cost < 0) {
                $errors[] = "Ingredient #{$idx}: negative unit cost ({$cost}).";
            }

            if ($id === null) {
                $errors[] = "Ingredient #{$idx}: missing material reference (item_id).";
            }

            if ($id !== null) {
                if (isset($seen[$id])) {
                    $errors[] = "Ingredient #{$idx}: duplicate material (item_id={$id}) — already at row #{$seen[$id]}.";
                }
                $seen[$id] = $idx;
            }
        }

        return $errors;
    }

    /** Ensure final_cost > 0 and margin is non-negative when a selling price exists. */
    public function validateCostIntegrity(array $breakdown): array
    {
        $errors = [];

        if (bccomp($breakdown['final_cost'], '0', self::SCALE) < 0) {
            $errors[] = "Final cost is negative ({$breakdown['final_cost']}).";
        }

        if (isset($breakdown['margin_percent']) && $breakdown['margin_percent'] !== null) {
            if (bccomp($breakdown['margin_percent'], '0', 6) < 0) {
                $errors[] = "Negative margin ({$breakdown['margin_percent']}). Selling price does not cover total cost.";
            }
        }

        return $errors;
    }
}
