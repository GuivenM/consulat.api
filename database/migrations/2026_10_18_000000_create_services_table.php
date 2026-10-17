<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Services consulaires gérés depuis l'espace admin (nom, description, ordre,
 * visibilité). Le `code` est la clé reprise par `tarifs.type_demande`,
 * `document_types_requis.type_demande` et `demandes.type` : il est généré à la
 * création et ne change plus.
 *
 * Les types déjà utilisés par les tarifs et les pièces requises sont repris
 * automatiquement, avec les textes qui étaient jusqu'ici codés dans la page
 * publique pour la carte consulaire et le laissez-passer.
 */
return new class extends Migration
{
    private const CONNUS = [
        'carte_consulaire' => [
            'Carte consulaire',
            "Document qui atteste de votre inscription au registre consulaire et facilite vos démarches auprès des autorités béninoises.",
        ],
        'laissez_passer' => [
            'Laissez-passer',
            "Titre de voyage provisoire délivré en cas de perte, de vol ou d'expiration de votre passeport congolais.",
        ],
    ];

    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();

            $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('nom', 150);
            $table->text('description')->nullable();
            $table->unsignedInteger('ordre')->default(0);
            $table->boolean('est_actif')->default(true);

            $table->timestamps();

            $table->unique(['entity_id', 'code'], 'services_entite_code_unique');
        });

        // Reprise des types déjà configurés (tarifs et pièces requises).
        $existants = DB::table('tarifs')->select('entity_id', 'type_demande')
            ->union(DB::table('document_types_requis')->select('entity_id', 'type_demande'))
            ->get()
            ->groupBy('entity_id');

        foreach ($existants as $entityId => $lignes) {
            $codes = $lignes->pluck('type_demande')->unique()->values()->all();
            $connus = array_values(array_filter(array_keys(self::CONNUS), fn ($c) => in_array($c, $codes, true)));
            $autres = array_values(array_diff($codes, $connus));

            foreach (array_merge($connus, $autres) as $i => $code) {
                [$nom, $description] = self::CONNUS[$code]
                    ?? [Str::headline(str_replace('_', ' ', $code)), null];

                DB::table('services')->insert([
                    'entity_id' => $entityId,
                    'code' => $code,
                    'nom' => $nom,
                    'description' => $description,
                    'ordre' => $i + 1,
                    'est_actif' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
