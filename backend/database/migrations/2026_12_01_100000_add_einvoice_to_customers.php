<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // E-Rechnung (XRechnung): Rechnungen dieses Kunden werden zusätzlich zum PDF als XML erzeugt und archiviert.
        // buyer_reference ist die Käuferreferenz der Rechnung (BT-10), bei öffentlichen Auftraggebern die Leitweg-ID.
        Schema::table('customers', function (Blueprint $table) {
            $table->boolean('einvoice')->default(false)->after('payment_term_days');
            $table->string('buyer_reference', 46)->nullable()->after('einvoice');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['einvoice', 'buyer_reference']);
        });
    }
};
