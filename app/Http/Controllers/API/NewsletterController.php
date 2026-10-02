<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\NewsletterAbonne;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NewsletterController extends Controller
{
    /**
     * Inscription à la newsletter (route publique, appelée depuis le footer
     * et toute autre page du site).
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email|max:255',
                'source' => 'nullable|string|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                ], 422);
            }

            $email = strtolower($request->email);
            $abonne = NewsletterAbonne::where('email', $email)->first();

            if ($abonne && $abonne->statut === 'actif') {
                return response()->json([
                    'success' => true,
                    'message' => 'Cet email est déjà inscrit à la newsletter.',
                    'data' => $abonne,
                ], 200);
            }

            if ($abonne) {
                // Ancien désinscrit qui se réinscrit.
                $abonne->update([
                    'statut' => 'actif',
                    'desinscrit_le' => null,
                    'source' => $request->source ?: $abonne->source,
                ]);
            } else {
                $abonne = NewsletterAbonne::create([
                    'email' => $email,
                    'source' => $request->source,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Inscription à la newsletter confirmée. Merci !',
                'data' => $abonne,
            ], 201);
        } catch (\Exception $e) {
            report($e); // journalisé côté serveur, jamais renvoyé au client
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'inscription à la newsletter'
            ], 500);
        }
    }

    /**
     * Liste des abonnés actifs (admin uniquement).
     */
    public function index()
    {
        try {
            $abonnes = NewsletterAbonne::actif()->orderBy('created_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => $abonnes,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des abonnés',
            ], 500);
        }
    }

    /**
     * Désinscrire un abonné (admin uniquement).
     */
    public function destroy($id)
    {
        try {
            $abonne = NewsletterAbonne::findOrFail($id);
            $abonne->update([
                'statut' => 'desinscrit',
                'desinscrit_le' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Abonné désinscrit avec succès',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la désinscription',
            ], 500);
        }
    }
}
