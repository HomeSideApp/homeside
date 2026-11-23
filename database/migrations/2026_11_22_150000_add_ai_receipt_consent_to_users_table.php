<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Permiso del usuario para que el análisis de un ticket use un proveedor de
     * IA que **no** corre en su propio servidor.
     *
     * El pase determinístico (OCR + parser) no necesita permiso: corre siempre y
     * no manda nada afuera. Cuando falla y el proveedor configurado es cloud, la
     * decisión es del usuario, se guarda con fecha (auditoría) y se puede
     * revocar poniéndola en null.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('ai_receipt_consent_at')->nullable()->after('locale');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('ai_receipt_consent_at');
        });
    }
};
