<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create event_product_categories table
        Schema::create('event_product_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->integer('order_column')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['event_id', 'slug']);
            $table->index('order_column');
        });

        // 2. Add category_id FK to event_products
        Schema::table('event_products', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('event_id')
                ->constrained('event_product_categories')->nullOnDelete();

            $table->index('category_id');
        });

        // 3. Add category_id FK to order_items
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('event_product_id')
                ->constrained('event_product_categories')->nullOnDelete();

            $table->index('category_id');
        });

        // 4. Populate event_product_categories from distinct (event_id, category) in event_products
        $distinctCategories = DB::table('event_products')
            ->select('event_id', 'category')
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('event_id')
            ->orderBy('category')
            ->get();

        $orderByEvent = [];

        foreach ($distinctCategories as $row) {
            if (! isset($orderByEvent[$row->event_id])) {
                $orderByEvent[$row->event_id] = 1;
            }

            $baseSlug = Str::slug($row->category);
            if (empty($baseSlug)) {
                $baseSlug = 'category';
            }

            // Check for slug uniqueness within the event
            $slug = $baseSlug;
            $counter = 1;
            while (DB::table('event_product_categories')
                ->where('event_id', $row->event_id)
                ->where('slug', $slug)
                ->exists()
            ) {
                $slug = $baseSlug.'-'.$counter;
                $counter++;
            }

            DB::table('event_product_categories')->insert([
                'event_id' => $row->event_id,
                'title' => $row->category,
                'slug' => $slug,
                'order_column' => $orderByEvent[$row->event_id]++,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 5. Update event_products.category_id based on matching (event_id, category) -> (event_id, title)
        DB::statement('
            UPDATE event_products
            SET category_id = epc.id
            FROM event_product_categories epc
            WHERE event_products.event_id = epc.event_id
            AND event_products.category = epc.title
        ');

        // 6. Update order_items.category_id via event_products
        DB::statement('
            UPDATE order_items
            SET category_id = ep.category_id
            FROM event_products ep
            WHERE order_items.event_product_id = ep.id
            AND ep.category_id IS NOT NULL
        ');

        // 7. Drop old string columns and obsolete index
        Schema::table('event_products', function (Blueprint $table) {
            $table->dropIndex('event_products_event_id_category_index');
            $table->dropColumn('category');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('product_category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore old columns
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('product_category')->nullable();
        });

        Schema::table('event_products', function (Blueprint $table) {
            $table->string('category')->default('');
            $table->index(['event_id', 'category'], 'event_products_event_id_category_index');
        });

        // Restore data from event_product_categories
        DB::statement('
            UPDATE event_products
            SET category = epc.title
            FROM event_product_categories epc
            WHERE event_products.category_id = epc.id
        ');

        DB::statement('
            UPDATE order_items
            SET product_category = epc.title
            FROM event_product_categories epc
            WHERE order_items.category_id = epc.id
        ');

        // Drop category_id FKs
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });

        Schema::table('event_products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });

        Schema::dropIfExists('event_product_categories');
    }
};
