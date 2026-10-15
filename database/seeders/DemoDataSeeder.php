<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Product;
use App\Models\Size;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\Lookup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * OPTIONAL sample products WITH stock, so every screen has something to show while testing
 * (some items are on low stock and some are out of stock, so the dashboard alerts have data).
 *
 *   php artisan db:seed --class=DemoDataSeeder
 *
 * Safe to run again: a product whose name already exists is skipped, so nothing is duplicated and
 * your real products are never touched. Every starting quantity is recorded in Stock History
 * as a "Received - Opening stock (sample data)" entry, the same way real stock is recorded.
 * Real products are added through Products > Add Product, and stock through Inventory > Receive.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::where('role', 'owner')->first();

        // name, category, brand, price, [ [size, color, quantity, low-stock alert, own price (optional)], ... ]
        $catalog = [
            ['Trail Slippers', 'Slippers', 'Treklite', 450, [
                ['40', 'Black', 12, 5], ['41', 'Black', 10, 5], ['42', 'Black', 3, 5], ['43', 'Black', 8, 5], ['42', 'Olive', 0, 5],
            ]],
            ['River Sandals', 'Slippers', 'TUKAD', 650, [
                ['40', 'Brown', 6, 4], ['41', 'Brown', 9, 4], ['42', 'Brown', 7, 4], ['43', 'Black', 2, 4],
            ]],
            ['Quick-Dry Shirt', 'Apparel', 'Treklite', 650, [
                ['M', 'Navy', 8, 4], ['L', 'Navy', 6, 4], ['XL', 'Navy', 5, 4], ['L', 'Gray', 3, 4],
            ]],
            ['Trekking Pants', 'Apparel', 'Treklite', 1250, [
                ['30', 'Khaki', 5, 3], ['32', 'Khaki', 7, 3], ['34', 'Khaki', 4, 3],
            ]],
            ['Rain Jacket', 'Apparel', 'TrailPro', 1450, [
                ['M', 'Yellow', 4, 3], ['L', 'Yellow', 5, 3], ['L', 'Green', 0, 3],
            ]],
            ['Hiking Backpack 40L', 'Bags', 'Summit Gear', 2800, [
                [null, 'Green', 6, 3], [null, 'Black', 8, 3],
            ]],
            ['Dry Bag 10L', 'Bags', 'TrailPro', 380, [
                [null, 'Yellow', 15, 5], [null, 'Blue', 12, 5], [null, 'Orange', 4, 5],
            ]],
            ['2-Person Tent', 'Tents & Shelter', 'Summit Gear', 3500, [
                [null, 'Green', 5, 2], [null, 'Orange', 2, 2],
            ]],
            ['Tarp 3x3m', 'Tents & Shelter', 'TrailPro', 600, [
                [null, null, 15, 5],
            ]],
            ['Sleeping Bag', 'Sleeping', 'Summit Gear', 1100, [
                [null, 'Green', 9, 4], [null, 'Blue', 6, 4],
            ]],
            ['Foam Sleeping Mat', 'Sleeping', 'TrailPro', 450, [
                [null, null, 10, 4],
            ]],
            ['Camp Stove', 'Cooking', 'TrailPro', 850, [
                [null, null, 7, 3],
            ]],
            ['Cook Set', 'Cooking', 'TrailPro', 950, [
                [null, null, 3, 3],
            ]],
            ['Headlamp', 'Lighting', 'Summit Gear', 550, [
                [null, 'Black', 14, 5], [null, 'Blue', 0, 5],
            ]],
            ['Trekking Pole', 'Accessories', 'Summit Gear', 700, [
                [null, 'Black', 4, 5, 750], [null, 'Silver', 11, 5],
            ]],
            ['Water Bottle 1L', 'Accessories', 'TrailPro', 320, [
                [null, 'Steel', 25, 6], [null, 'Black', 18, 6],
            ]],
            ['Camping Towel', 'Textiles', null, 250, [
                [null, null, 20, 5],
            ]],
        ];

        $added = 0;

        foreach ($catalog as [$name, $category, $brand, $price, $variants]) {
            if (Product::where('name', $name)->exists()) {
                continue;   // already there: leave it exactly as it is
            }

            DB::transaction(function () use ($owner, $name, $category, $brand, $price, $variants) {
                $product = Product::create([
                    'name'        => $name,
                    'category_id' => Lookup::id(Category::class, $category),
                    'brand_id'    => Lookup::id(Brand::class, $brand),
                    'price'       => $price,
                    'is_active'   => true,
                ]);

                foreach ($variants as $row) {
                    [$size, $color, $qty, $low] = $row;

                    $variant = $product->variants()->create([
                        'size_id'             => Lookup::id(Size::class, $size),
                        'color_id'            => Lookup::id(Color::class, $color),
                        'price'               => $row[4] ?? null,
                        'initial_stock'       => $qty,
                        'quantity'            => $qty,
                        'low_stock_threshold' => $low,
                    ]);

                    if ($qty > 0 && $owner) {
                        StockMovement::create([
                            'variant_id'      => $variant->id,
                            'user_id'         => $owner->id,
                            'type'            => 'receive',
                            'quantity_before' => 0,
                            'quantity_change' => $qty,
                            'quantity_after'  => $qty,
                            'note'            => 'Opening stock (sample data)',
                        ]);
                    }
                }
            });

            $added++;
        }

        $this->command?->info($added . ' sample product(s) added' . ($added < count($catalog) ? ', ' . (count($catalog) - $added) . ' already existed and were skipped.' : '.'));
    }
}
