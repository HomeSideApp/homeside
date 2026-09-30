<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_records', function (Blueprint $table) {
            $table->unsignedInteger('source_order')->default(0)->after('contact_source_id');
        });

        DB::table('contact_records')->whereNotNull('contact_source_id')
            ->orderBy('id')->chunk(200, function ($records): void {
                foreach ($records as $record) {
                    $labelIds = DB::table('contact_contact_label')
                        ->where('contact_id', $record->contact_id)
                        ->where('imported', true)
                        ->pluck('contact_label_id')->all();
                    $metadata = json_decode($record->provider_metadata ?: '{}', true);
                    if (! is_array($metadata)) {
                        $metadata = [];
                    }
                    $metadata['category_label_ids'] = $labelIds;
                    DB::table('contact_records')->where('id', $record->id)
                        ->update(['provider_metadata' => json_encode($metadata)]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('contact_records', function (Blueprint $table) {
            $table->dropColumn('source_order');
        });
    }
};
