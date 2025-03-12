<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DataController;
use App\Http\Controllers\FavoriteSourceController;
use App\Http\Middleware\RequestRole;
use App\Models\ReadyFeed;
use App\Models\RssFeedModel;
use App\Services\Scrapping;
use Illuminate\Database\Schema\Blueprint;
<<<<<<< HEAD
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
=======
use App\Http\Controllers\DataController;
>>>>>>> publishing

/**
 * Authentication Routes
 */
Route::post('/sign-in', [AuthController::class, 'signIn']);
Route::post('/user/create', [AuthController::class, 'store']);

<<<<<<< HEAD
/**
 * Protected User Route (Requires Authentication)
 */
Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    $user = Auth::guard('sanctum')->user();
    if (!$user) {
        return response()->json(['message' => 'No authenticated user'], 401);
    }
    return response()->json($user);
});

/**
 * Data Fetching Routes (Protected)
 */
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/data', [DataController::class, 'getFeedsData']);
    Route::get('/data/{id}', [DataController::class, 'getSpeceficData']);
    Route::get('/publishedArticles', [DataController::class, 'getReadyData']);
    Route::post('/article/publish/{id}', [DataController::class, 'publishArticle']);
    Route::get('/ready_feeds', fn() => response()->json(ReadyFeed::all()));
});
=======






Route::middleware(['auth.api:sanctum'])->group(function () {
    Route::get('/user', function (Request $request) {
        return response()->json($request->user());
    });
    Route::get('/data', [DataController::class, 'getFeedsData']);
    Route::get('/ready_feeds', [DataController::class, 'getReadyFeeds']);
});

Route::get('/data/{id}', [DataController::class, 'getSpeceficData']);
Route::post('/article/{id}/publish', [DataController::class, 'publishArticle']);

>>>>>>> publishing

/**
 * Scraping Route
 */
Route::post('/data', function (Request $request) {
    $scrapping = new Scrapping();
    $result = $scrapping->scrappe();
    return response()->json($result);
});

/**
 * Favorite Sources Routes (Protected)
 */
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/favorite_sources/store', [FavoriteSourceController::class, 'store']);
    Route::post('/favorite_sources/fetch', [FavoriteSourceController::class, 'fecth']);
});

/**
 * Duplicate Removal Route for RSS Feeds
 */
Route::post('/rssfeeds/remove-duplicates', function () {
    try {
        DB::beginTransaction();
        
        $normalizeContent = fn($text) => Str::lower(trim(preg_replace('/[^\p{L}\p{N}\s]/u', '', strip_tags($text))));
        
        $duplicates = DB::table('rss_feeds')
            ->select('title', 'description', DB::raw('MIN(id) as keep_id'))
            ->groupBy('title', 'description')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        $deletedCount = 0;
        $processedTitles = [];

        foreach ($duplicates as $duplicate) {
            $contentKey = md5($normalizeContent($duplicate->title) . $normalizeContent($duplicate->description));
            if (in_array($contentKey, $processedTitles)) continue;

            $records = RssFeedModel::where('title', $duplicate->title)
                ->where('description', $duplicate->description)
                ->where('id', '!=', $duplicate->keep_id)
                ->pluck('id')->toArray();

            foreach (array_chunk($records, 100) as $chunk) {
                $deletedCount += RssFeedModel::whereIn('id', $chunk)->delete();
            }
            
            Log::info('Removed duplicate RSS feed entries', ['title' => $duplicate->title, 'count' => count($records), 'kept_id' => $duplicate->keep_id]);
            $processedTitles[] = $contentKey;
        }

        DB::commit();
        return response()->json(['success' => true, 'deleted_count' => $deletedCount]);
    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Failed to remove duplicates', ['error' => $e->getMessage()]);
        return response()->json(['success' => false, 'message' => 'Failed to remove duplicates'], 500);
    }
});

/**
 * Temporary Routes (For Development Only)
 */
Route::get('/create-favorite-sources', function () {
    if (!Schema::hasTable('favorite_sources')) {
        Schema::create('favorite_sources', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id');
            $table->text('source');
            $table->timestamps();
        });
        return response()->json(['message' => 'Table created successfully.']);
    }
    return response()->json(['message' => 'Table already exists.']);
});

Route::get('/reset-users-table', function () {
    Schema::dropIfExists('users');
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('role');
        $table->string('password');
        $table->string('api_token')->nullable();
        $table->rememberToken();
        $table->timestamp('email_verified_at')->nullable();
        $table->timestamps();
    });
    return response()->json(['message' => 'Users table reset successfully.']);
});
<<<<<<< HEAD
=======


Route::get('/data/ready',function () {
    return response()->json(ReadyFeed::all());
});


Route::get('/publishedArticles', [App\Http\Controllers\DataController::class, 'getReadyData']);


Route::get('ts', function () {
    // Hardcoded data
    $rssFeeds = [
        [
            "source" => "https://appweb.regione.vda.it/DBWeb/Comunicati.nsf/RSScomunicati.xml",
            "title" => "Approvato dalla Giunta regionale il calendario ittico 2025",
            "link" => "https://appweb.regione.vda.it/dbweb/Comunicati.nsf/VediNewsi/2343524AD7A41725C1258C49003A41CB?OpenDocument",
            "description" => "Indietro L’Assessorato dell’Agricoltura e Risorse naturali rende noto che, durante la riunione di lunedì 10 marzo, la Giunta regionale ha approvato il Calendario ittico 2025, documento che, su proposta dell'Assessore e sentito il Consorzio regionale per la tutela, l'incremento e l'esercizio della pesca in Valle d'Aosta, definisce ogni anno le specie pescabili e le relative modalità di prelievo, i periodi, le giornate e gli orari di pesca, le zone in cui è vietata la pesca e quelle in cui vige un regime particolare di prelievo e ogni altra indicazione ritenuta utile al fine di una corretta attività alieutica. “Il calendario ittico è un documento importante che permette al Consorzio di avviare l’iter della pesca, materia sulla quale il confronto con il MASAF è costante, in particolare riguardo ai limiti imposti sull’immissione ittica nei nostri laghi e torrenti” – spiega l’Assessore Marco Carrel e aggiunge “Per il 2025 abbiamo dovuto tenere conto e recepire le limitazioni contenute dalla DGR n.916/2024 “Obiettivi e misure di conservazione per le ZSC della Rete Natura 2000 della Valle d’Aosta”. L’inizio della stagione di pesca è stato fissato per il 30 marzo 2025. @vdaufficiale regionevalledaosta.official Regione Valle d’Aosta Regione Autonoma Valle d’Aosta RegVdA0214 usFonte: Assessorato Agricoltura e Risorse naturali – Ufficio stampa Regione autonoma Valle d’Aosta/Vallée d’Aoste         Indietro",
            "pubDate" => "2025-03-10T10:36:00.000000Z",
            "isPublished" => 0,
        ],
        [
            "source" => "https://appweb.regione.vda.it/DBWeb/Comunicati.nsf/RSScomunicati.xml",
            "title" => "Autorizzazioni di nuovi impianti viticoli: domande entro il 31 marzo 2025",
            "link" => "https://appweb.regione.vda.it/dbweb/Comunicati.nsf/VediNewsi/0AFDB76645CCB0C9C1258C49003A0728?OpenDocument",
            "description" => "Indietro L’Assessorato dell’Agricoltura e Risorse naturali comunica che sino a lunedì 31 marzo 2025 è possibile presentare le domande per le autorizzazioni di nuovi impianti viticoli in base a quanto previsto dal Decreto del Ministero dell’Agricoltura, della Sovranità alimentare e delle Foreste n. 649010 del 16 dicembre 2022. Le domande devono essere presentate in modalità telematica nell’ambito del SIAN, recandosi presso l’Ufficio Produzioni vegetali dell’Assessorato, in località La Maladière - Rue de la Maladière n. 39 a Saint-Christophe, aperto al pubblico il martedì e il giovedì dalle ore 9 alle ore 14 e su appuntamento gli altri giorni (per informazioni 0165.275324). I richiedenti devono presentarsi con il fascicolo aziendale preventivamente validato dal centro di assistenza agricola di competenza. Su fascicolo dovrà risultare una superficie aziendale in conduzione pari o superiore a quella richiesta per i nuovi impianti, dove non sia già presente un vigneto e senza vincoli all’impianto. La superficie massima richiedibile per domanda è stabilita in 4 ettari. Le autorizzazioni saranno rilasciate dalla Regione, indicativamente nel mese di agosto, sulla base dell’elenco trasmesso dal Ministero e hanno validità di tre anni dalla data di rilascio. Per informazioni, contattare l’Ufficio Produzioni vegetali ai numeri 0165.27 53 24 o 0165.27 53 92. @vdaufficiale regionevalledaosta.official Regione Valle d’Aosta Regione Autonoma Valle d’Aosta RegVdA0213 usFonte: Assessorato Agricoltura e Risorse naturali – Ufficio stampa Regione autonoma Valle d’Aosta/Vallée d’Aoste         Indietro",
            "pubDate" => "2025-03-10T10:33:00.000000Z",
            "isPublished" => 0,
        ],
        [
            "source" => "https://appweb.regione.vda.it/DBWeb/Comunicati.nsf/RSScomunicati.xml",
            "title" => "I principali provvedimenti della Giunta regionale",
            "link" => "https://appweb.regione.vda.it/dbweb/Comunicati.nsf/VediNewsi/98A5BAFB244EDAD3C1258C490039D843?OpenDocument",
            "description" => "Indietro PRESIDENZA DELLA REGIONE È stato approvato uno schema di convenzione tra la Regione e l’Università degli studi di Padova per lo svolgimento presso le strutture dell’Amministrazione regionale, entro i limiti delle possibilità di tutoraggio e accoglienza da valutarsi volta per volta, di tirocini curricolari, al fine di agevolare le scelte professionali mediante la conoscenza diretta del mondo del lavoro e realizzare momenti di alternanza tra studio e lavoro nell’ambito dei processi formativi. AFFARI EUROPEI, INNOVAZIONE, PNRR E POLITICHE NAZIONALI PER LA MONTAGNA È stata presa in esame la deliberazione relativa all’”Approvazione delle schede relative alle modalità di impiego delle risorse a valere sul fondo per lo sviluppo delle montagne italiane (Fosmit) – parte regionale, per l’anno 2024, di cui al Decreto del Ministro per gli Affari regionali e le autonomie dell’11 dicembre 2024, e del relativo avviso pubblico per la concessione di finanziamenti a favore dei comuni e delle Unités des communes valdôtaines”. Tali risorse sono destinate a finanziare progetti presentati dagli enti locali della Regione e risultati ammissibili e finanziabili. AGRICOLTURA E RISORSE NATURALI È stato approvato il Piano degli interventi in amministrazione diretta del Dipartimento risorse naturali e Corpo forestale per il triennio 2025/2027. Il piano prevede, per la sua realizzazione, l’assunzione, in aggiunta al personale a tempo indeterminato, di 350 lavoratori da assumere a tempo determinato con contratto di lavoro degli addetti idraulico-forestali, per la durata di 151 giornate lavorative per gli anni 2025, 2026 e 2027. Le assunzioni degli operai a tempo determinato saranno effettuate scorrendo le graduatorie vigenti delle procedure selettive bandite ai sensi dell’art. 6 della legge regionale 21/2017. La deliberazione approvata sarà trasmessa alla Commissione consiliare competente per materia per il parere. È stato approvato il calendario ittico 2025. Rispetto alla stagione precedente, il calendario prevede l’utilizzo, nelle acque libere, di ami senza ardiglione o con ardiglione schiacciato per agevolare la slamatura del pescato al fine di una più responsabile attività alieutica; il divieto della pesca, nel periodo estivo, nel lago di Brusson per consentirne la navigabilità ai fini turistici; lo svolgimento di due manifestazioni internazionali di pesca, nel periodo estivo, nel lago del Gran San Bernardo; l’istituzione di tre nuove riserve di pesca: il lago La Palud al Col de Joux in Comune di Saint-Vincent; il lago Verney al Colle del Piccolo San Bernardo in Comune di La Thuile; la Dora della Val Ferret in Comune di Courmayeur. Sono stati approvati i nuovi criteri e le modalità per il rilascio del parere di razionalità per i fabbricati rurali. Con tale atto vengono ridefinite e precisate le procedure per il rilascio del parere di razionalità, rivisto il dimensionamento aziendale minimo per l’edificazione agricola, rinnovata e semplificata l’applicazione degli standard costruttivi e definito il dimensionamento dei fabbricati rurali. TURISMO, SPORT E COMMERCIO Sono stati concessi contributi a fondo perso, per l’anno 2025, alle sei associazioni iscritte nell’elenco regionale di cui all’articolo 4 della l.r. 6 del 2004 - Adiconsum Valle d’Aosta, A.D.O.C. Valle d’Aosta; A.V.C.U Valle d’Aosta; Cittadinanzattiva Valle d’Aosta; Codacons Valle d’Aosta; Federconsumatori Valle d’Aosta - per un ammontare complessivo di 79 mila e 999,84 euro, pari al 40,81% della spesa ammessa complessiva, ammontante a 196 mila 030,00 euro. È stato concesso un contributo forfetario annuo pari a 330 mila euro all’organismo sportivo ASIVA – Comitato valdostano FISI, destinato al sostegno della gestione dell’attività agonistica a livello di rappresentative regionali, dell’attività di indirizzo, di coordinamento e sostegno dell’attività svolta dagli sci club operanti nella regione, nonché dell’attività di orientamento propedeutico alla formazione professionale dei giovani che intendono intraprendere l’insegnamento dello sci relativa all’anno 2025. È stato concesso un contributo forfetario annuo pari a 30 mila euro a favore della Delegazione regionale Valle d’Aosta del Comitato Italiano Paralimpico (CIP) destinato a incentivare e a sostenere l’attività sportiva relativa all’anno 2025 praticata dagli atleti con disabilità. È stato, infine, concesso un contributo a favore dell’Associazione Sportiva Dilettantistica Valdostana Martze a Pià (ASDVMAP) destinato al sostegno dell’attività promozionale volta a favorire lo sviluppo delle manifestazioni di corsa in montagna, anche sotto il profilo turistico-promozionale e del rispetto dell’ambiente, relativa all’anno 2025. @vdaufficiale regionevalledaosta.official Regione Valle d’Aosta Regione Autonoma Valle d’Aosta RegVdA 0212 us Fonte: Presidenza della Regione – Ufficio stampa Regione Autonoma Valle d’Aosta/Vallée d’Aoste         Indietro",
            "pubDate" => "2025-03-10T10:31:00.000000Z",
            "isPublished" => 0,
        ],
        [
            "source" => "https://appweb.regione.vda.it/DBWeb/Comunicati.nsf/RSScomunicati.xml",
            "title" => "03/10/2025 - 9:59 - Modifiche alla circolazione lungo le strade regionali di Valgrisenche, Bionaz e del Colle d’Arpy",
            "link" => "https://appweb.regione.vda.it/dbweb/Comunicati.nsf/VediNewsi/61BFB1420FC11C2EC1258C490031614D?OpenDocument",
            "description" => "Indietro L’Assessorato delle Opere pubbliche, Territorio e Ambiente informa che: - per consentire l’esecuzione di lavori di collaudo del ponte “Leverogne”, è disposta la chiusura della SR 25 di Valgrisenche, nel Comune di Arvier, dal km 0+580 al km 0+650, dalle ore 8.30 alle ore 12.00 di venerdì 14 marzo 2025 (MAP). - per consentire l’intervento urgente di sostituzione giunti sul viadotto lungo la strada regionale, è disposta l’istituzione di un senso unico alternato, regolato da impianto semaforico mobile o da movieri lungo un tratto della SR 28 Bionaz, nel Comune di Gignod, tra il km. 1+880 e il km. 1+980 in località Chez Henry, dalle ore 08.00 di oggi 10 marzo alle ore 18.00 di lunedì 24 marzo 2025 (MAP) - al fine d’agevolare il regolare deflusso del traffico veicolare e di consentire l’utilizzo di parte della carreggiata per la sosta di veicoli in occasione delle gare di Coppa del Mondo di sci alpino, è disposta l’istituzione di un senso unico di marcia con direzione obbligatoria lungo un tratto della SR 39 del Colle d’Arpy, nel Comune di La Thuile, tra la rotatoria al km. 17+470 in località Villaret e la strettoia stradale al km. 18+500 in località Bathieu 17, dalle ore 00.00 di venerdì 14 marzo alle ore 24.00 di sabato 15 marzo 2025 (MAP) 0211 us Fonte: Assessorato delle Opere pubbliche, Territorio e Ambiente – Ufficio stampa Regione Autonoma Valle d’Aosta         Indietro",
            "pubDate" => "2025-03-10T08:59:00.000000Z",
            "isPublished" => 0,
        ],
        [
            "source" => "https://appweb.regione.vda.it/DBWeb/Comunicati.nsf/RSScomunicati.xml",
            "title" => "Impegni del Presidente e degli Assessori dall’8 al 17 marzo 2025",
            "link" => "https://appweb.regione.vda.it/dbweb/Comunicati.nsf/VediNewsi/0228865E4BFF1A44C1258C46004F00B1?OpenDocument",
            "description" => "Indietro Sabato 8 marzo 2025 ore 18.30 – Bard, Sede MAB (Piazza Cavour, ex Hotel Stendhal) Il Presidente Renzo Testolin e gli Assessori Luigi Bertschy, Giulio Grosjacques e Jean-Pierre Guichardaz partecipano all’inaugurazione della sede del MAB - Maison des Artistes di Bard aperta nell’ambito del Progetto Transit – Turismo culturale sostenibile ore 20.30 – Aosta, Sala Viglino Il Presidente Renzo Testolin e l'Assessore Giulio Grosjacques intervengono alla presentazione dell’anteprima Aosta, una Roma tra le Alpi Lunedì 10 marzo 2025 ore 8.00 - Aosta, Sala Giunta Riunione della Giunta regionale Ore 11.00 - Saletta adiacente alla Sala Maria Ida Viglino Conferenza stampa di presentazione dei provvedimenti della Giunta regionale ore 11.30 – Aosta, Saletta adiacente alla Sala Maria Ida Viglino Il Presidente Renzo Testolin e l’Assessore Davide Sapinet partecipano alla Conferenza stampa sulle iniziative che saranno promosse in occasione dell’Anno internazionale conservazione ghiacciai ore 17.30 – Aosta, Università della Valle d’Aosta L’Assessore Carlo Marzi interviene all’evento Il benessere psicologico e la cura di sé nei giovani adulti ore 10.00 – In Videoconferenza L’Assessore Luciano Caveri presiede la riunione politica della Commissione Politiche per la montagna della Conferenza delle Regioni e delle Province Autonome ore 18.00 – Aosta, Biblioteca regionale Bruno Salvadori Il Presidente Renzo Testolin interviene alla presentazione del libro di Régis Brunod e classe VB 2023/2024 Liceo Artistico Le chalet-garage de Paris-Levallois 20h30 – Aoste, Théâtre Splendor L’assesseur Jean-Pierre Guichardaz est présent au concert En Chantant – concert de L’A.R.Co.V.A Martedì 11 marzo 2025 ore 08.00 - 13.00 Aosta (Sede assessorato) L’Assessore Davide Sapinet riceve gli amministratori e il pubblico su appuntamento ore 8.30-12.00 – Pollein (Sede Assessorato) L’Assessore Giulio Grosjacques riceve su appuntamento amministratori e cittadini ore 9.00 – Quart, vivaio forestale regionale “Abbé Henry” L’Assessore Marco Carrel interviene durante il seminario Piante, siepi e alberature mellifere utili agli apoidei organizzato in collaborazione con il Consorzio Apistico della Valle d’Aosta ore 09.00 – Aosta, Palazzo Darbelley (Sede Assessorato) L’Assessore Carlo Marzi riceve il pubblico su appuntamento ore 09.30 – Aosta, Palazzo regionale L’Assessore Marco Carrel è audito dalla Terza Commissione Consiliare permanente in merito al ddl n. 177: “Disposizioni in materia di aiuti regionali per la compensazione dei danni causati dalla fauna selvatica al patrimonio zootecnico e ittico e alle produzioni vegetali, nonché pe l’audizione di misure di prevenzione. Modificazioni alla l.r. 27 agosto 1994, n. 64 (Norme per la tutela e la gestione della fauna selvatica e per la disciplina dell’attività venatoria)”. ore 11.00-13.00 – Saint-Christophe (Sede Assessorato) L’Assessore Marco Carrel riceve amministratori e pubblico su appuntamento ore 11.00 – Aosta, Biblioteca regionale Bruno Salvadori Il Presidente Renzo Testolin e l’Assessore Jean-Pierre Guichardaz intervengono alla conferenza stampa di presentazione di Aosta 2025 ore 14.30 – Aosta, Saletta adiacente alla Sala Maria Ida Viglino Il Presidente Renzo Testolin e l’Assessore Jean-Pierre Guichardaz intervengono alla Conferenza stampa di presentazione della 49ème Rencontre Valdôtaine ore 17.30– Aosta, Auditorium BCC L’Assessore Jean-Pierre Guichardaz è presente all’incontro su Violenza di genere e tutela della persona offesa nella legislazione penale: bilanci e prospettive con Manlio D'Ambrosi Mercoledì 12 marzo 2025 ore 9.00 – Aosta, aula consiliare Adunanza del Consiglio regionale Giovedì 13 marzo 2025 ore 9.00 – Aosta, aula consiliare Adunanza del Consiglio regionale 20h30 – Aoste, Théâtre Splendor L’Assesseur Jean-Pierre Guichardaz est présent au concert En Chantant – concert de L’A.R.Co.V.A Venerdì 14 marzo 2025 ore 11.00 – La Thuile Il Presidente Renzo Testolin e gli Assessori Luciano Caveri, Giulio Grosjacques, Jean-Pierre Guichardaz e Davide Sapinet sono presenti alla Gara di Coppa del Mondo di discesa di sci Alpino femminile 20h30 – Aoste, Cinéma Théâtre de la Ville L’Assesseur Jean-Pierre Guichardaz est présent à la projection du documentaire Je me souviens Sabato 15 marzo 2025 ore 11.00 – La Thuile Il Presidente Renzo Testolin e gli Assessori Luciano Caveri, Giulio Grosjacques, Jean-Pierre Guichardaz e Davide Sapinet sono presenti alla Gara di Coppa del Mondo di discesa di sci Alpino femminile ore 10.00 – Saint-Christophe, Sala Conferenze biblioteca comunale L’Assessore Marco Carrel partecipa all’incontro annuale della sezione Ovina e Caprina dell’A.R.E.V. ore 16.00 – Bard, Forte di Bard L’Assessore Davide Sapinet partecipa al Convegno di apertura degli eventi regionali dell’Anno Internazionale per la Conservazione dei Ghiacciai Ghiacciai e diritto: nuove regole per un bene comune compromesso dal riscaldamento climatico Lunedì 17 marzo 2025 ore 8.00 - Aosta, Sala Giunta Riunione della Giunta regionale ore 9.15 – Videoconferenza L’Assessore Luciano Caveri partecipa alla riunione politica della Commissione Innovazione tecnologica e Digitalizzazione della Conferenza delle Regioni e delle Province Autonome Gli impegni del Presidente e degli Assessori possono variare durante il corso della settimana. Si consiglia di verificare gli orari prima degli appuntamenti. 209 us Fonte: Presidenza della Regione – Ufficio stampa Regione Autonoma Valle d’Aosta/Vallée d’Aoste         Indietro",
            "pubDate" => "2025-03-07T14:22:00.000000Z",
            "isPublished" => 0,
        ],
        [
            "source" => "https://appweb.regione.vda.it/DBWeb/Comunicati.nsf/RSScomunicati.xml",
            "title" => "Modifiche alla circolazione lungo la strada regionale di Antagnod",
            "link" => "https://appweb.regione.vda.it/dbweb/Comunicati.nsf/VediNewsi/4AF1B005F0225B9EC1258C46004E3CB9?OpenDocument",
            "description" => "Indietro L’Assessorato delle Opere pubbliche, Territorio e Ambiente informa che per consentire i lavori di realizzazione di un tratto di marciapiedi, è disposta l’istituzione di un senso unico alternato, regolato da impianto semaforico mobile o da movieri, lungo un tratto della SR n.5 di Antagnod, nel Comune di Ayas, tra il km 3+800 e il km 3+900 in località Antagnod, dalle ore 7.30 di lunedì 10 marzo alle ore 17.30 di mercoledì 30 aprile 2025, sabato, domenica e festivi esclusi (MAP). 0209 us Fonte: Assessorato delle Opere pubbliche, Territorio e Ambiente – Ufficio stampa Regione Autonoma Valle d’Aosta         Indietro",
            "pubDate" => "2025-03-07T14:14:00.000000Z",
            "isPublished" => 0,
        ],
        [
            "source" => "https://appweb.regione.vda.it/DBWeb/Comunicati.nsf/RSScomunicati.xml",
            "title" => "Conférence de presse - Présentation de la 49e Rencontre valdôtaine en programme le 3 août à Saint-Nicolas",
            "link" => "https://appweb.regione.vda.it/dbweb/Comunicati.nsf/VediNewsi/EA211FD6D6EB5351C1258C46004CBD9E?OpenDocument",
            "description" => "Indietro La Présidence de la Région communique qu'une conférence de presse est convoquée mardi 11 mars, à 14 h 30, dans la petite salle attenante à la salle Maria Ida Viglino du Palais régional, pour présenter la 49e édition de la Rencontre valdôtaine, qui se déroulera cette année à Saint-Nicolas. À la conférence de presse interviendront le président de la Région, Renzo Testolin, l'assesseur aux activités et aux biens culturels, au système éducatif et aux politiques des relations intergénérationnelles, Jean-Pierre Guichardaz, la syndique de Saint-Nicolas, Marlène Domaine, les représentants de l'Association Valdôtaine Fiolet, Marco Gheller de la Fondation Chanoux, Elisabetta Dugros, qui suit les activités menées par l'Assessorat pour la promotion des sociétés savantes, et Bruno Zanivan du Centre d’études francoprovençales René Willien de Saint-Nicolas. 0208 us Source : Présidence de la Région / Assessorat des activités et des biens culturels, du système éducatif et des politiques des relations intergénérationnelles – Bureau de presse de la Région autonome Valle d’Aosta/Vallée d’Aoste         Indietro",
            "pubDate" => "2025-03-07T13:58:00.000000Z",
            "isPublished" => 0,
        ],
        [
            "source" => "https://appweb.regione.vda.it/DBWeb/Comunicati.nsf/RSScomunicati.xml",
            "title" => "Percorsi abilitanti per docenti in Valle d’Aosta: stato dell’arte, criticità e azioni intraprese",
            "link" => "https://appweb.regione.vda.it/dbweb/Comunicati.nsf/VediNewsi/371BD15A8012A921C1258C460048F69F?OpenDocument",
            "description" => "Indietro L’Assessorato dei Beni e Attività culturali, Sistema educativo e Politiche per le relazioni intergenerazionali informa che mercoledì 5 marzo 2025 si è svolto un incontro tra il Presidente della Regione, l’Assessore alle Attività e ai Beni Culturali, al Sistema Educativo e alle Politiche delle Relazioni Intergenerazionali, l’Università della Valle d’Aosta e le organizzazioni sindacali, incentrato sui percorsi abilitanti per i docenti della scuola secondaria. L’obiettivo della riunione è stato quello di fare il punto sulle criticità emerse a livello nazionale e locale, facendo luce sulle rispettive competenze e attribuzioni di responsabilità di tutti gli attori istituzionali coinvolti nei percorsi e ha permesso di valorizzare le azioni già intraprese dall’Assessorato e dall’Università della Valle d’Aosta per supportare i docenti valdostani in questo percorso. Il quadro normativo e i ritardi ministeriali L’attivazione dei percorsi abilitanti a livello nazionale è stata segnata da notevoli ritardi: ad oggi non sono ancora stati emanati tutti i decreti ministeriali necessari all’avvio dei percorsi, generando incertezza per i docenti, gli Atenei e le amministrazioni. Questa situazione ha determinato il mancato avvio dei corsi su tutto il territorio nazionale, complicando, in particolare, la programmazione dei percorsi obbligatori per coloro che devono completare l’abilitazione entro il 31 agosto 2025, termine fissato per i vincitori di concorso a tempo determinato. Le criticità in Valle d’Aosta Le difficoltà nazionali si intrecciano con alcune caratteristiche specifiche del contesto valdostano. 1. L’offerta formativa abilitante in Valle d’Aosta Attraverso la collaborazione consolidata nell’ambito del CIFIS (Consorzio Interuniversitario per la Formazione degli Insegnanti), l’Università della Valle d’Aosta ha garantito una riserva di posti a favore di candidati residenti in Valle d’Aosta per le classi di abilitazione in relazione alle quali è stato rilevato un fabbisogno di personale nelle istituzioni scolastiche regionali, ha inoltre garantito la possibilità di svolgere tutti i cfu di tirocinio diretto presso le scuole valdostane e la possibilità di seguire a distanza tutti i cfu di didattiche trasversali. L’Università della Valle d’Aosta, non essendo in condizione di sostenere, per difetto di organico docente e amministrativo, la proposta di attivazione di un’offerta formativa, presso la propria sede, relativa ai percorsi abilitanti per la scuola secondary nell’anno accademico 2024/2025 entro il termine fissato dal Ministero per l’accreditamento dei percorsi abilitanti (dicembre 2024), ha definito in maniera puntuale le azioni necessarie per assicurare, in futuro e nell’ambito del consorzio CIFIS, specifici percorsi abilitanti sul territorio regionale. 2. Classi di concorso in attesa di accreditamento Presso il CIFIS sono ancora in fase di accreditamento, perché in attesa del riscontro da parte del Ministero, i percorsi per alcune classi di abilitazione tra cui quella di Francese, particolarmente rilevante per il territorio regionale. È altamente probabile che tali classi vengano accreditate presso il CIFIS tant’è che è già prevista la possibilità di presentare domanda. La situazione è, malauguratamente, ancora in evoluzione in attesa delle disposizioni ministeriali. 3. Accesso garantito ai vincitori di concorso Il CIFIS ha specificato che saranno ammessi in sovrannumero i docenti in possesso di una nomina finalizzata al ruolo ottenuta per l'a.s. 2024/2025 e chiarito che la disposizione vale sia per coloro che hanno sede di servizio in Piemonte, sia per coloro che hanno sede di servizio in Valle d’Aosta. 4. Permessi per il diritto allo studio e contributi a favore di laureati che intendono completare la loro preparazione con corsi post-universitari Per sostenere i docenti valdostani impegnati nei percorsi abilitanti, è stato firmato il Contratto integrativo regionale (CIR) per la fruizione dei permessi per il diritto allo studio 2025. Tale Contratto introduce un’importante novità: la possibilità di usufruire fino a 50 ore di permessi studio per chi è impegnato nei percorsi di abilitazione da 30 e 36 CFU, che si concluderanno entro agosto 2025. L’Amministrazione regionale, considerando la situazione attuale, intende, inoltre, ampliare le agevolazioni previste per i laureati che vogliono completare la loro formazione con corsi post-universitari. In particolare, nel prossimo bando di concorso, sarà estesa anche ai docenti che devono conseguire i 30 o 36 CFU fuori Valle per la loro prima abilitazione all’insegnamento la possibilità di beneficiare dei contributi economici. Si tratta di due misure concrete ottenute grazie al lavoro congiunto tra l’Assessorato, la Sovraintendenza agli Studi e le parti sindacali, con l’obiettivo di supportare i docenti nel conseguimento dell’abilitazione entro i tempi previsti dal Ministero. 5. Impatto sugli esami di Stato del secondo ciclo di istruzione La concomitanza tra i percorsi abilitanti e gli esami di Stato del secondo ciclo di istruzione pone un’ulteriore criticità: alcuni docenti coinvolti potrebbero avere incarichi di commissari esterni, creando difficoltà organizzative nella formazione delle commissioni d’esame. L’Assessorato sta monitorando la situazione per individuare possibili soluzioni per il corretto svolgimento delle prove senza penalizzare i docenti in fase di abilitazione. 6. Tutela delle lavoratrici in maternità Un altro tema particolarmente delicato riguarda la tutela delle lavoratrici in maternità. L’attuale normativa non prevede alcuna deroga o modalità di recupero per chi, a causa della maternità, non riesca a completare il percorso entro agosto 2025. Su questa criticità, peraltro già emersa in relazione ai percorsi abilitanti per il sostegno, e già segnalata al Ministero, l’Assessorato e l’Università auspicano che, anche grazie all’intervento delle organizzazioni sindacali, si adottino al più presto misure atte a evitare discriminazioni nei confronti delle suddette docenti. In conclusione, l’Assessorato e l’Università ribadiscono la volontà di lavorare in sinergia con le organizzazioni sindacali e con il Ministero per individuare soluzioni concrete e sostenibili, garantendo continuità e qualità nella formazione degli insegnanti, anche nella prospettiva dell’entrata a regime dei percorsi abilitanti. 207 us Fonte: Assessorato dei Beni e Attività culturali, Sistema educativo e Politiche per le relazioni intergenerazionali – Ufficio stampa Regione Autonoma Valle d’Aosta/Vallée d’Aoste         Indietro",
            "pubDate" => "2025-03-07T13:16:00.000000Z",
            "isPublished" => 0,
        ],
        [
            "source" => "https://appweb.regione.vda.it/DBWeb/Comunicati.nsf/RSScomunicati.xml",
            "title" => "38ma edizione delle « Rencontres de Physique de la Vallée d’Aoste »",
            "link" => "https://appweb.regione.vda.it/dbweb/Comunicati.nsf/VediNewsi/90EE4393ECC58313C1258C46003EF4CE?OpenDocument",
            "description" => "Indietro L’Assessorato dei Beni e Attività culturali, Sistema educativo e Politiche per le relazioni intergenerazionali comunica che da lunedì 10 a sabato 15 marzo 2025, al Centro Congressi dell'hotel Planibel di La Thuile è in programma la 38ma edizione delle \"Rencontres de Physique de la Vallée d'Aoste\". Si tratta di un grande evento scientifico di livello internazionale, organizzato dall’Assessorato ai Beni e alle attività culturali, Sistema educativo e Politiche per le relazioni intergenerazionali della Regione Autonoma Valle d’Aosta, in collaborazione con l’I.N.F.N. - Istituto Nazionale di Fisica Nucleare, che con cadenza annuale, riunisce fisici da tutto il mondo per discutere gli ultimi sviluppi nel campo della fisica delle particelle, della fisica nucleare e della fisica teorica, per fare il punto sullo stato della ricerca nel settore delle particelle elementari, i più piccoli costituenti della materia. Il Comune di La Thuile offrirà come sempre il suo sostegno all’iniziativa. I temi trattati, con più di 50 relazioni, saranno quelli sulla fisica delle astroparticelle e sulla ricerca della materia oscura: nuovi dati sul cosmo profondo insieme alle osservazioni di onde gravitazionali, forniscono un quadro innovativo del nostro Universo e aprono a nuove prospettive (e interrogativi) nella fisica delle particelle. Les Rencontres de Physique rappresentano non solo un'eccellenza nel campo della ricerca fisica, ma anche un'importante occasione di visibilità per il nostro territorio, capace di attrarre ricercatori di altissimo livello da tutto il mondo con un appuntamento che contribuisce a rafforzare il posizionamento della regione come destinazione privilegiata per il turismo congressuale di alto profilo. Tra gli spin off di questa edizione, si ricorda HEPscape! una escape room sulla fisica delle alte energie, rivolta agli studenti delle scuole secondarie di 1^ e 2^ e al pubblico (https://web.infn.it/hepscape/descrizione-del-progetto/). L’attività aperta al pubblico, ad ingresso libero fino al raggiungimento dei posti disponibili, si svolgerà presso il Teatro Splendor di Aosta nei pomeriggi di mercoledì 12 e giovedì 13 marzo 2025 dalle 15.20 alle 17.00. Per maggiori informazioni è possibile consultare il sito internet: https://www.pi.infn.it/lathuile/lathuile_2025.html 206 us Fonte: Assessorato dei Beni e Attività culturali, Sistema educativo e Politiche per le relazioni intergenerazionali – Ufficio stampa Regione Autonoma Valle d’Aosta/Vallée d’Aoste         Indietro",
            "pubDate" => "2025-03-07T11:27:00.000000Z",
            "isPublished" => 0,
        ],
        [
            "source" => "https://appweb.regione.vda.it/DBWeb/Comunicati.nsf/RSScomunicati.xml",
            "title" => "Marzo al Megamuseo di Aosta: al via il ciclo di esposizioni Ti racconto un oggetto e i public talk",
            "link" => "https://appweb.regione.vda.it/dbweb/Comunicati.nsf/VediNewsi/9F1F80B7E726FA89C1258C46003CD4DC?OpenDocument",
            "description" => "Indietro L’Assessorato dei Beni e Attività culturali, Sistema educativo e Politiche per le relazioni intergenerazionali informa che al MegaMuseo di Aosta, a partire dal mese di marzo, prendono avvio due nuovi progetti, pensati per avvicinare il pubblico al patrimonio archeologico e storico del museo, attraverso nuovi allestimenti e dialoghi con esperti. Si tratta di un ciclo di esposizioni bimestrali dal titolo Ti racconto un oggetto e il programma di incontri Il MegaMuseo si racconta. Un filo rosso lega il debutto di entrambe le attività: il rapporto tra passato e presente, tra oggetti e storie, tra materia e memoria. Un tema che emerge chiaramente nella prima esposizione di “Ti racconto un oggetto”, dedicata alle lucerne romane e al loro ruolo tra vita quotidiana e ritualità funeraria, così come nel primo public talk, che esplorerà i riti funerari dell’antica Roma e il legame tra vita e morte. In questo nuovo ciclo di esposizioni bimestrali un reperto del MegaMuseo entrerà in dialogo con un manufatto del MAR–Museo Archeologico Regionale di Aosta. “Ti racconto un oggetto” è stato infatti ideato anche per rafforzare il legame tra le istituzioni museali valdostane, evidenziando le connessioni tra le loro collezioni. Ogni due mesi, una vetrina dedicata metterà in luce oggetti, solitamente esposti nel loro contesto abituale, offrendo al pubblico un’occasione per osservarli con uno sguardo diverso. Grazie a una grafica distintiva e a un approfondimento testuale, questi reperti racconteranno le loro storie, svelando il loro significato storico e culturale. Con queste due iniziative, il MegaMuseo di Aosta si vuole affermare non solo come luogo di conservazione del patrimonio, ma anche come spazio di dialogo e ricerca. Il confronto tra reperti e voci esperte permette di mettere in evidenza come la storia sia una realtà in continua trasformazione che può essere letta e interpretata da molteplici prospettive. Dal 13 marzo, quindi, il primo appuntamento di “Ti racconto un oggetto” si intitola Facciamo luce sulle lucerne, ed è dedicato alle lucerne romane, oggetti di uso quotidiano, ma anche strumenti rituali. La lucerna esposta al MegaMuseo proviene dalla “Tomba dello scriba”, una sepoltura di fine I secolo d.C. caratterizzata da un corredo particolarmente ricco. Il manufatto è decorato con una scena tratta dall’Odissea, raffigurante Ulisse che sfugge a Polifemo, nascosto sotto un montone. Ad accompagnarla, una moneta di Domiziano, il cosiddetto obolo per Caronte, che testimonia la pratica funeraria di lasciare un’offerta per il traghettatore delle anime nell’aldilà. Diversa la storia della lucerna proveniente dal MAR – Museo Archeologico Regionale di Aosta, rinvenuta in un’abitazione romana di Augusta Prætoria, nota come “Domus della Fontana”. A distinguerla è il materiale: non terracotta, ma bronzo, più raro e prezioso. Particolarmente interessante è la sua ansa, decorata con una maschera tragica, un elemento che richiama il mondo del teatro e trova riscontri in altre case romane dell’epoca. Accostando questi due oggetti, l’esposizione evidenzia come la luce, nel mondo romano, non fosse solo una necessità quotidiana, ma anche un simbolo legato alla vita, alla memoria e al passaggio nell’aldilà. Parallelamente a “Ti racconto un oggetto”, il MegaMuseo inaugura anche il ciclo di incontri MegaMuseo si racconta, un programma di incontri che offrirà al pubblico l’opportunità di esplorare il patrimonio del museo attraverso il racconto diretto di esperti, in dialogo con il direttore Generoso Urciuoli. Agli eventi è presente anche l’archeologa Cinzia Joris che evidenzia i parallelismi tra passato e presente, mettendo in relazione riti e consuetudini millenari con il nostro tempo. Il 13 marzo alle ore 17, il primo incontro avrà come tema I riti funerari del mondo romano: non solo reperti eccezionali, ma storie di legami stretti tra vivi e morti. In un dialogo aperto, la referente scientifica del Museo Alessandra Armirotti e il direttore del MegaMuseo Generoso Urciuoli esploreranno le pratiche funerarie dell’antica Roma, mettendo in luce non solo i reperti esposti, ma anche il modo in cui i Romani vivevano il rapporto con la morte, attraverso rituali, oggetti e credenze. Nei successivi appuntamenti, il ciclo vedrà la partecipazione di personalità di spicco provenienti da diversi ambiti, tutti accomunati da un approccio innovativo alla conoscenza e alla cultura. Tra gli ospiti attesi ci saranno il 27 marzo alle ore 17. Andrea Nicola, farmacista, in dialogo con il direttore del museo Generoso Urciuoli e l’archeologa Cinzia Joris sul tema dell’Eterna bellezza in un confronto su reperti, cosmetici e materie prime naturali. https://valledaostaheritage.com/events/marzo-2025/ INFORMAZIONI Struttura Patrimonio storico-artistico e gestione siti culturali T. + 39 0165 274327/67 205 us Fonte: Assessorato dei Beni e Attività culturali, Sistema educativo e Politiche per le relazioni intergenerazionali – Ufficio stampa Regione Autonoma Valle d’Aosta/Vallée d’Aoste Allegati:Megamuseo talk .jpg Megamuseo talk 2.jpg         Indietro",
            "pubDate" => "2025-03-07T11:04:00.000000Z",
            "isPublished" => 0,
        ],
    ];

    $savedItems = [];
    $errors = [];

    try {
        foreach ($rssFeeds as $item) {
            // Clean the title if it contains a date prefix (e.g., "DD/MM/YYYY - HH:MM - ")
            $cleanedTitle = preg_replace('/^\d{2}\/\d{2}\/\d{4}\s*-\s*\d{1,2}:\d{2}\s*-\s*/', '', $item['title']);
            $cleanedTitle = trim($cleanedTitle);

            // Check for duplicates by link
            if (RssFeedModel::where('link', $item['link'])->exists()) {
                $errors[] = "Duplicate link found: {$item['link']}";
                continue;
            }
                        // Insert into the database
                        $rssFeed = RssFeedModel::create([
                            'source' => $item['source'],
                            'title' => $cleanedTitle,
                            'link' => $item['link'],
                            'description' => $item['description'],
                            'pubDate' => $item['pubDate'],
                            'isPublished' => $item['isPublished'],
                        ]);
            
                        $savedItems[] = $rssFeed; // Add the saved item to the array
                    }
            
                    // Return success response with saved items and any errors
                    return response()->json([
                        'status' => 'success',
                        'message' => 'RSS feeds imported successfully',
                        'saved_items' => $savedItems,
                        'errors' => $errors,
                    ], 200);
            
                } catch (\Exception $e) {
                    // Log the error and return an error response
                    Log::error("Error importing RSS feeds: " . $e->getMessage());
            
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Failed to import RSS feeds',
                        'error' => $e->getMessage(),
                    ], 500);
                }
            });
>>>>>>> publishing
