<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guide_documents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('sous_section_id')->constrained('guide_sous_sections')->cascadeOnDelete();
            $table->string('titre');
            $table->text('description')->nullable();
            $table->string('fichier'); // chemin du fichier dans le disque de stockage
            $table->string('type_fichier')->nullable(); // pdf, docx, xlsx, etc.
            $table->unsignedBigInteger('taille')->default(0); // en octets
            $table->unsignedInteger('telechargements')->default(0);
            $table->enum('statut', ['publie', 'brouillon'])->default('brouillon');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guide_documents');
    }
};
