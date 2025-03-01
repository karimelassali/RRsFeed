<?php

namespace App\Http\Controllers;


use Illuminate\Support\Facades\Http;

class AiController extends Controller
{
    public function gemini()
    {
        $apiKey = env('GEMINI_API_KEY');
        $adminPrompt = 'ALways summurize this feed descreption in 5-9 lines and extract its location.here its the prompt:';
        $response = Http::post('https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=' . $apiKey, [
            'contents' => [
                [
                    'parts' => [
                        ['text' =>$adminPrompt . ' Municipium Descrizione Per consentire lo svolgimento delle cerimonie di apertura e di chiusura dei 39/i Campionati italiani di sci dei Vigili del Fuoco, in programma dal 16 al 18 gennaio sulle nevi di Pila e di Cogne con la partecipazione prevista di oltre 1.000 atleti, tramite un’ordinanza sono state istituite le seguenti modifiche temporanee alla circolazione.Dalle ore 12 di giovedì 16 gennaio, alle ore 12 di sabato 18 gennaio: divieto di sosta con rimozione forzata nel piazzale adibito a parcheggio sito sul lato Nord della carreggiata di corso Lancieri d’Aosta di fronte al Palaghiaccio. Giovedì 16 gennaio, dalle ore 13 alle ore 21: divieto di sosta con rimozione forzata in piazza Cavalieri di Vittorio Veneto e in via Mazzini. Giovedì 16 gennaio, dalle ore 17 alle ore 19 (e comunque per il tempo necessario allo svolgimento della sfilata): divieto di transito per tutti i veicoli, compresi quelli condotti da titolari di permesso Ztl e quelli al servizio del Trasporto pubblico di linea, in viale Conseil des Commis lato Nord, piazza Chanoux, via Porta Prætoria, via Sant’Anselmo, piazza Arco d’Augusto e via Garibaldi, nel tratto compreso tra piazza Arco d’Augusto e l’intersezione a rotatoria con via Torino. Sabato 18 gennaio, dalle ore 11 alle ore 17: divieto di sosta con rimozione forzata in via Mazzini.
                        ']
                    ]
                ]
            ]
        ]);
        $data = $response->json();
        return response()->json([
            'response' => $data['candidates'][0]['content']['parts'][0]['text'],
        ]);
    }
}



