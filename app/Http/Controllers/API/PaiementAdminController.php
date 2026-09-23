<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Demande;
use App\Models\JournalActivite;
use App\Models\Paiement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Encaissement au guichet. Le paiement en ligne (FedaPay) suivra dans un
 * contrôleur séparé — les deux canaux écrivent dans la même table
 * `paiements`, PaiementObserver recalcule `demandes.paiement_statut` dans
 * les deux cas de la même façon.
 *
 * Toujours le montant plein de la demande (pas de paiement partiel) : la
 * grille tarifaire est un forfait fixe par délai, pas un montant
 * négociable — voir Paiement::encaisserAuGuichet().
 */
class PaiementAdminController extends Controller
{
    public function encaisser(Request $request, int $demandeId)
    {
        $validator = Validator::make($request->all(), [
            'mode' => 'required|in:' . implode(',', array_keys(Paiement::MODES_GUICHET)),
            'numero_recu' => 'required|string|max:100|unique:paiements,numero_recu',
            'nom_payeur' => 'nullable|string|max:150',
            'telephone_payeur' => 'nullable|string|max:30',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $demande = Demande::find($demandeId);

        if (!$demande) {
            return response()->json([
                'success' => false,
                'message' => 'Demande introuvable',
            ], 404);
        }

        if ($demande->paiement_statut === 'paye') {
            return response()->json([
                'success' => false,
                'message' => 'Cette demande est déjà payée.',
            ], 422);
        }

        if (in_array($demande->statut, ['retire', 'rejete'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cette demande est close, aucun paiement ne peut plus y être ajouté.',
            ], 422);
        }

        $paiement = Paiement::encaisserAuGuichet($demande, $validator->validated(), $request->user()->id);

        JournalActivite::enregistrer(
            'paiement.guichet',
            "Paiement guichet enregistré pour le dossier {$demande->numero_dossier} (reçu {$paiement->numero_recu})",
            $paiement,
            ['montant' => $paiement->montant, 'mode' => $paiement->mode]
        );

        return response()->json([
            'success' => true,
            'message' => 'Paiement enregistré.',
            'data' => [
                'id' => $paiement->id,
                'montant' => $paiement->montant,
                'devise' => $paiement->devise,
                'numero_recu' => $paiement->numero_recu,
                'paiement_statut' => $demande->fresh()->paiement_statut,
            ],
        ], 201);
    }
}
