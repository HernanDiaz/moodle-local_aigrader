<?php
// This file is part of Moodle - https://moodle.org/.
//
// Moodle is free software: you can redistribute it and/or modify.
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the.
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License.
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Catalan language strings for AI Grader Pro.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'AI Grader Pro';

// Descripcions de capabilities (vistes a Administració del lloc > Permisos).
$string['aigrader:use'] = 'Usar qualificació assistida per IA en tasques';
$string['aigrader:configure'] = 'Configurar AI Grader Pro en una tasca';
$string['aigrader:viewlog'] = 'Veure registre d\'auditoria d\'AI Grader Pro';

// Pàgina de configuració de l\'administrador.
$string['setting_enabled'] = 'Habilita el connector';
$string['setting_enabled_desc'] = 'Interruptor global d\'AI Grader Pro. Quan està desactivat, els professors no poden llançar noves qualificacions IA en cap tasca. Els registres d\'auditoria existents es conserven.';

$string['setting_rubric_autoimport'] = 'Importa automàticament des de la rúbrica de qualificació';
$string['setting_rubric_autoimport_desc'] = 'Quan una tasca utilitza el mètode de qualificació per rúbrica de Moodle, omple automàticament els criteris d\'avaluació d\'AI Grader Pro amb el contingut de la rúbrica. El professor pot editar els criteris importats abans d\'habilitar la qualificació IA.';

$string['setting_default_system_prompt'] = 'Prompt de sistema per defecte';
$string['setting_default_system_prompt_desc'] = 'Instrucció institucional opcional que s\'afegeix al system prompt de cada sol·licitud de qualificació. Útil per imposar un to o política consistents entre tots els professors. Exemple: "Aporta feedback constructiu en registre acadèmic, màxim 200 paraules." Deixa-ho buit per usar només el system prompt per defecte del connector.';

// Formulari d\'edició de la tasca (mod_assign).
$string['form_enabled'] = 'Habilita la qualificació assistida per IA en aquesta tasca';
$string['form_enabled_help'] = 'Quan està marcat, els professors poden executar AI Grader Pro sobre els lliuraments d\'aquesta tasca. La IA proposa nota i feedback; el professor revisa i decideix. No es publica res a l\'alumne fins que el professor aprova.';

$string['form_criteria'] = 'Criteris d\'avaluació';
$string['form_criteria_help'] = 'Descripció en llenguatge natural de com ha d\'avaluar la IA els lliuraments d\'aquesta tasca. Escriu les mateixes instruccions que donaries a un becari. Esmenta els criteris concrets, el seu pes relatiu, i el to de feedback que vols. Exemple:

Avalua aquest assaig (800-1000 paraules) sobre digitalització educativa segons aquests criteris:
- Claredat de la tesi (25%): és defensable la postura?
- Qualitat de les evidències (30%): les fonts són acadèmiques i ben citades?
- Estructura (25%): introducció, desenvolupament, conclusió
- Llenguatge (20%): registre acadèmic, ortografia

To: constructiu i específic, en català.';

$string['form_criteria_imported_notice'] = 'Criteris pre-omplerts des de la rúbrica configurada a "Qualificació > Qualificació avançada". Pots editar-los abans d\'habilitar la qualificació IA.';

$string['form_model_override'] = 'Model (opcional)';
$string['form_model_override_help'] = 'Si el defineixes, aquesta tasca usa aquest model específic en lloc del per defecte del proveïdor IA. Útil quan vols un model més capaç (o més barat) per a una tasca concreta. Deixa-ho buit per usar el per defecte global.';

$string['form_language_override'] = 'Idioma del feedback (opcional)';
$string['form_language_override_help'] = 'Si el defineixes, el feedback de la IA per a aquesta tasca anirà en aquest idioma en lloc de l\'idioma del curs. Deixa-ho a "Auto" per usar l\'idioma del curs.';

$string['form_lang_auto'] = 'Auto (usa l\'idioma del curs)';

// Errors de validació.
$string['error_criteria_required'] = 'Els criteris d\'avaluació són obligatoris quan la qualificació assistida per IA està habilitada. Descriu com ha d\'avaluar la IA els lliuraments.';

// Importador de rúbriques.
$string['rubric_export_header'] = 'Criteris (importats automàticament de la rúbrica de qualificació avançada de la tasca):';

// Tasques adhoc (vistes a Administració del lloc > Servidor > Tasques).
$string['task_grade_submission'] = 'AI Grader Pro: qualifica un lliurament';
$string['errortaskfailed'] = 'La tasca de qualificació d\'AI Grader Pro ha fallat: {$a}';

// Pàgina de gestió (/local/aigrader/manage.php).
$string['manage_pagetitle']         = 'AI Grader Pro · {$a}';
$string['manage_heading']           = 'AI Grader Pro: {$a}';
$string['manage_disabled']          = 'AI Grader Pro no està habilitat en aquesta tasca. Edita la configuració de la tasca per activar-lo.';
$string['manage_no_submissions']    = 'Encara no hi ha lliuraments per a aquesta tasca.';
$string['manage_polling']           = 'Una qualificació està en curs. Aquesta pàgina s\'actualitzarà automàticament.';
$string['manage_back_to_assignment'] = '← Torna a la tasca';
$string['msg_enqueued']             = 'Tasca de qualificació IA encolada. S\'executarà al pròxim cron.';
$string['msg_graded_now']           = 'Qualificació IA completada. Prem Revisar per veure la proposta.';
$string['msg_needs_manual_review']  = 'La IA no ha pogut processar aquest lliurament automàticament. Prem Revisar per qualificar manualment.';

$string['th_student']   = 'Alumne';
$string['th_submitted'] = 'Lliurat';
$string['th_status']    = 'Estat IA';
$string['th_grade']     = 'Nota proposada';
$string['th_action']    = 'Acció';

$string['btn_grade_with_ai']   = 'Qualifica amb IA';
$string['btn_pending']         = 'Processant...';

$string['status_none']        = 'Sense qualificació IA';
$string['status_pending']     = 'Pendent';
$string['status_proposed']    = 'Proposta IA';
$string['status_reviewed']    = 'Revisada pel professor';
$string['status_published']   = 'Publicada';
$string['status_error']       = 'Error';
$string['status_unsupported'] = 'Format no suportat';

$string['errornotenabled']  = 'AI Grader Pro no està habilitat en aquesta tasca.';
$string['errornocriteria']  = 'No hi ha criteris d\'avaluació definits per a aquesta tasca.';

// Pàgina de revisió (/local/aigrader/review.php).
$string['review_pagetitle']       = 'Revisa la proposta IA · {$a}';
$string['review_heading']         = 'Revisa la proposta IA: {$a->assign} — {$a->student}';
$string['review_submission_text'] = 'Lliurament de l\'alumne';
$string['review_proposed']        = 'Nota i feedback proposats (editables)';
$string['review_criterion_scores'] = 'Puntuació per criteri (de la IA, informativa)';
$string['review_proposed_at']     = 'Proposta feta el {$a}';
$string['review_proposed_by']     = 'per {$a}';
$string['manualfallback_banner']  = 'La qualificació amb IA no estava disponible per a aquest lliurament, per la qual cosa el formulari és buit. Omple nota i feedback manualment; "Aprova i publica" els escriu al quadern de qualificacions igual que amb les propostes IA. Motiu:';
$string['manualfallback_default'] = 'no hi ha proposta IA registrada per a aquest lliurament.';
$string['review_submission_files']        = 'Fitxers adjunts';
$string['review_submission_seen_by_ai']   = 'Lliurament tal com el va veure la IA';
$string['review_seen_by_ai_help']         = 'Aquesta és la versió que la IA va llegir del fitxer de l\'alumne. Si la proposta IA diu alguna cosa estranya, comprova aquí quin text va rebre realment. Alguns formats no es processen (PDFs molt grans, imatges).';
$string['review_seen_by_ai_size']         = '{$a} KB de text extrets.';
$string['review_seen_by_ai_warnings']     = 'Avisos sobre l\'extracció:';

$string['field_finalgrade']         = 'Nota final';
$string['field_finalgrade_points']  = 'Nota final (sobre {$a})';
$string['field_finalgrade_reference'] = 'Nota orientativa (0-10, no es publica)';
$string['review_advanced_grading']  = 'Aquesta tasca fa servir qualificació avançada ({$a}). L\'AI Grader Pro encara no la pot omplir, així que no pot publicar la nota des d\'aquí. Fes servir la proposta com a referència i completa la qualificació al qualificador de Moodle. Pots desar els canvis aquí sense publicar.';
$string['review_open_grader']       = 'Obre el qualificador de Moodle per a aquest alumne';
$string['review_grade_none']        = 'Aquesta tasca està configurada sense qualificació: només es publicarà el feedback a l\'alumne.';
$string['field_strengths']          = 'Punts forts';
$string['field_strengths_hint']     = 'Un per línia. Es mostrarà a l\'alumne com a feedback positiu.';
$string['field_improvements']       = 'A millorar';
$string['field_improvements_hint']  = 'Un per línia. Suggeriments constructius que veurà l\'alumne.';
$string['field_justification']      = 'Justificació (visible per a l\'alumne)';

$string['btn_review']          = 'Revisa';
$string['btn_view_published']  = 'Veure ✓';
$string['btn_approve_publish'] = 'Aprova i publica';
$string['btn_save_draft']      = 'Desa sense publicar';

$string['msg_published']      = 'Nota aprovada i publicada al quadern de qualificacions.';
$string['msg_saved_draft']    = 'Desat sense publicar. La nota encara no és al quadern de qualificacions.';

$string['feedback_strengths']    = 'Punts forts';
$string['feedback_improvements'] = 'A millorar';
$string['feedback_justification'] = 'Resum';

$string['errornoproposal']      = 'No hi ha proposta IA disponible per a aquest lliurament.';
$string['errorparseproposal']   = 'La proposta IA desada no s\'ha pogut llegir. Torna a qualificar per regenerar-la.';
$string['errorgradeoutofrange'] = 'La nota ha d\'estar entre {$a->min} i {$a->max} (rebut: {$a->value}).';
$string['erroradvancedgrading'] = 'Aquesta tasca fa servir qualificació avançada (rúbrica o guia d\'avaluació), així que la nota no es pot publicar des de l\'AI Grader Pro. Qualifica-la al qualificador de Moodle.';

// Strings del Privacy provider.
$string['privacy:metadata'] = 'AI Grader Pro emmagatzema propostes de qualificació generades per IA, registres d\'auditoria de cada acció i configuració per tasca. També envia dades personals a un proveïdor LLM extern via l\'AI Subsystem de Moodle.';

// Taula local_aigrader_assign.
$string['privacy:metadata:assign']               = 'Configuració d\'AI Grader Pro per tasca (en quines tasques està habilitat, criteris d\'avaluació, i overrides). Emmagatzema l\'id del professor que va editar la configuració per última vegada.';
$string['privacy:metadata:assign:assignid']      = 'Id intern de la tasca.';
$string['privacy:metadata:assign:criteria_text'] = 'Criteris d\'avaluació escrits pel professor en llenguatge natural.';
$string['privacy:metadata:assign:usermodified']  = 'Id del professor que va editar la configuració per última vegada. S\'anonimitza en esborrar l\'usuari.';
$string['privacy:metadata:assign:timecreated']   = 'Moment en què la configuració es va desar per primera vegada.';
$string['privacy:metadata:assign:timemodified']  = 'Moment de l\'última modificació.';

// Taula local_aigrader_submission.
$string['privacy:metadata:submission']                   = 'Estat de la qualificació IA per lliurament: nota i feedback proposats, més la nota i feedback finals aprovats pel professor.';
$string['privacy:metadata:submission:submissionid']      = 'Id del lliurament de tasca al qual es refereix.';
$string['privacy:metadata:submission:studentid']         = 'Id de l\'alumne el lliurament del qual ha estat qualificat.';
$string['privacy:metadata:submission:status']            = 'Estat actual a la màquina d\'estats (pending_ai / ai_proposed / teacher_reviewed / published / error).';
$string['privacy:metadata:submission:proposed_grade']    = 'Nota proposada per la IA (0-10).';
$string['privacy:metadata:submission:proposed_feedback'] = 'Resposta completa de l\'LLM: puntuacions per criteri, punts forts, a millorar, justificació.';
$string['privacy:metadata:submission:final_grade']       = 'Nota aprovada pel professor (pot diferir de la proposta si el professor va editar).';
$string['privacy:metadata:submission:final_feedback']    = 'Feedback aprovat pel professor i mostrat a l\'alumne.';
$string['privacy:metadata:submission:final_grader']      = 'Id del professor que va aprovar la nota. S\'anonimitza en esborrar l\'usuari.';
$string['privacy:metadata:submission:timecreated']       = 'Moment en què es va encolar la primera qualificació IA.';
$string['privacy:metadata:submission:timemodified']      = 'Moment de l\'última modificació.';
$string['privacy:metadata:submission:timeprocessed']     = 'Moment en què va acabar la crida a l\'LLM.';
$string['privacy:metadata:submission:timepublished']     = 'Moment en què el professor va aprovar i la nota es va escriure al quadern de qualificacions.';

// Taula local_aigrader_log.
$string['privacy:metadata:log']                = 'Registre append-only de cada acció de qualificació IA. Exigit per l\'AI Act (Reg. 2024/1689 Annex III) per a sistemes IA d\'alt risc en educació.';
$string['privacy:metadata:log:userid']         = 'Id del professor que va disparar l\'acció. S\'anonimitza en esborrar l\'usuari.';
$string['privacy:metadata:log:studentid']      = 'Id de l\'alumne el lliurament del qual es va processar.';
$string['privacy:metadata:log:action']         = 'Tipus d\'acció registrada (grade, regrade, edit, approve, reject).';
$string['privacy:metadata:log:llm_provider']   = 'Nom del proveïdor LLM utilitzat (ex.: openai, azureai).';
$string['privacy:metadata:log:llm_model']      = 'Identificador del model LLM utilitzat (ex.: llama-3.3-70b-versatile).';
$string['privacy:metadata:log:prompt_text']    = 'Prompt complet enviat a l\'LLM (inclou el text del lliurament de l\'alumne).';
$string['privacy:metadata:log:response_json']  = 'Resposta crua de l\'LLM en JSON (inclou nota proposada i feedback).';
$string['privacy:metadata:log:tokens_input']   = 'Nombre de tokens d\'entrada consumits per la crida a l\'LLM.';
$string['privacy:metadata:log:tokens_output']  = 'Nombre de tokens de sortida consumits per la crida a l\'LLM.';
$string['privacy:metadata:log:proposed_grade'] = 'Nota proposada per l\'LLM al moment de l\'acció.';
$string['privacy:metadata:log:final_grade']    = 'Nota final després de la revisió del professor (si aplica).';
$string['privacy:metadata:log:teacher_edits']  = 'JSON diff que mostra com el professor va modificar la proposta IA.';
$string['privacy:metadata:log:timecreated']    = 'Moment en què es va registrar l\'acció.';

// Proveïdor LLM extern (dades transferides fora de Moodle).
$string['privacy:metadata:ai_subsystem']             = 'AI Grader Pro envia el text del lliurament de l\'alumne junt amb els criteris d\'avaluació del professor al proveïdor LLM configurat a l\'AI Subsystem de Moodle. El proveïdor pot estar allotjat dins o fora de la UE segons l\'elecció de la institució. L\'administrador del lloc signa un Data Processing Agreement (DPA) amb el proveïdor escollit.';
$string['privacy:metadata:ai_subsystem:prompt_text'] = 'Text del lliurament de l\'alumne junt amb els criteris i instruccions de qualificació del professor.';
$string['privacy:metadata:ai_subsystem:userid']      = 'Identificador d\'usuari passat al proveïdor LLM per a rate-limiting i prevenció d\'abús (s\'aplica la política de privacitat del proveïdor).';

// Banner d\'errors classificats (només professor, mai a l\'alumne).
$string['err_banner_title']         = 'La qualificació amb IA ha fallat';
$string['err_banner_title_plural']  = 'La qualificació amb IA ha fallat en {$a} lliuraments';
$string['err_banner_affecting']     = 'Afecta a: {$a}';
$string['err_banner_show_details']  = 'Veure l\'error tècnic';
$string['err_banner_retry']         = 'Torna a provar ara';

// Payload massa gran.
$string['err_payload_too_large_headline'] = 'El lliurament supera el límit del model';
$string['err_payload_too_large_body']     = 'El lliurament ocupa {$a->requested} tokens, però el model configurat "{$a->model}" només accepta {$a->limit} tokens per minut al pla actual.';
$string['err_payload_too_large_body_partial'] = 'El lliurament va superar el límit de tokens per minut del model configurat.';
$string['err_payload_too_large_action']   = 'Canvia a un model amb un límit TPM més alt a Administració del lloc → IA → Proveïdors, o demana a l\'alumne que elimini els outputs del notebook abans de tornar a lliurar.';

// No autoritzat.
$string['err_unauthorized_headline'] = 'El proveïdor ha rebutjat l\'API key';
$string['err_unauthorized_body']     = 'El proveïdor LLM ha retornat un error d\'autenticació. L\'API key no existeix, és invàlida o ha estat revocada.';
$string['err_unauthorized_action']   = 'Ves a Administració del lloc → IA → Proveïdors i revisa l\'API key del proveïdor actiu.';

// Rate limit.
$string['err_rate_limited_headline'] = 'Límit de peticions per minut superat';
$string['err_rate_limited_body']     = 'S\'han enviat massa peticions de qualificació en poc temps. Moodle tornarà a provar automàticament amb backoff exponencial.';
$string['err_rate_limited_action']   = 'No hi ha res a fer. La qualificació es reprendrà quan s\'alliberi la quota.';

// Error 5xx del proveïdor.
$string['err_provider_error_headline'] = 'Error temporal del proveïdor';
$string['err_provider_error_body']     = 'El proveïdor LLM ha retornat un error de servidor temporal. Moodle tornarà a provar automàticament.';
$string['err_provider_error_action']   = 'No hi ha res a fer. Si el problema persisteix més de 15 minuts, revisa la pàgina d\'estat del proveïdor.';

// Error de xarxa.
$string['err_network_error_headline'] = 'No s\'ha pogut connectar amb el proveïdor LLM';
$string['err_network_error_body']     = 'La connexió amb el proveïdor LLM ha fallat (timeout, error de DNS o connexió rebutjada).';
$string['err_network_error_action']   = 'Revisa la connectivitat de xarxa del lloc i l\'URL de l\'endpoint del proveïdor. Moodle tornarà a provar automàticament.';

// Error de parsing.
$string['err_parse_error_headline'] = 'L\'LLM ha retornat una resposta no vàlida';
$string['err_parse_error_body']     = 'El model va produir una sortida que no s\'ha pogut parsejar al format JSON de qualificació esperat.';
$string['err_parse_error_action']   = 'Prem "Torna a provar ara" per tornar a cridar el model. Si el problema persisteix, els criteris poden estar convidant a respostes en prosa lliure; revisa els criteris d\'avaluació.';

// Desconegut (catch-all).
$string['err_unknown_headline'] = 'La qualificació amb IA ha fallat';
$string['err_unknown_body']     = 'El proveïdor ha retornat un error: {$a}';
$string['err_unknown_action']   = 'Consulta els detalls a l\'audit log i torna-ho a intentar.';

// -----------------------------------------------------------------------.
// Bulk actions (manage.php: "Amb seleccionades..." selector + bulk.php).
// -----------------------------------------------------------------------.

// Barra d\'acció.
$string['bulk_label_with_selected'] = 'Amb seleccionades:';
$string['bulk_apply']               = 'Aplica';
$string['bulk_select_all']          = 'Selecciona totes les files';
$string['bulk_select_row']          = 'Selecciona el lliurament de {$a}';

// Opcions del selector.
$string['bulk_action_choose']          = '-- Tria una acció --';
$string['bulk_action_approve_publish'] = 'Publica la nota proposada';
$string['bulk_action_grade_ai']        = 'Qualifica amb IA';

// Avisos a la pantalla de confirmació.
$string['bulk_warning_approve_publish'] = 'Publicaràs les notes proposades per la IA tal com estan, sense editar. Les notes s\'escriuran al quadern de qualificacions i es notificarà l\'alumnat segons la configuració de la tasca. Aquesta acció no es pot desfer en bloc.';
$string['bulk_warning_grade_ai']        = 'Llançaràs la IA sobre els lliuraments seleccionats. Si algun ja té proposta, se sobreescriurà amb la nova. Cada lliurament consumeix tokens del proveïdor configurat.';

// Botons de confirmació.
$string['bulk_confirm_button_approve_publish'] = 'Sí, publica';
$string['bulk_confirm_button_grade_ai']        = 'Sí, qualifica';

// Pantalla de confirmació.
$string['bulk_confirm_pagetitle']       = 'AI Grader Pro · Confirma l\'acció';
$string['bulk_confirm_count']           = 'lliuraments es processaran.';
$string['bulk_confirm_skipped_header']  = 'Se saltaran:';

// Missatges d\'error / validació.
$string['bulk_no_selection']            = 'No has seleccionat cap lliurament.';
$string['errorinvalidaction']           = 'Acció en bloc no vàlida: {$a}';

// Resum post-execució (toast de redirect).
$string['bulk_done_ok']                 = '{$a} lliuraments processats';
$string['bulk_done_queued']             = '{$a} lliuraments en cua (el cron els completarà)';
$string['bulk_done_skipped']            = '{$a} saltats';
$string['bulk_done_errors']             = '{$a} amb error';

// Motius pels quals se salta una fila (mapejats a skip:<reason>).
$string['bulk_skip_already_published'] = 'Ja publicades';
$string['bulk_skip_in_flight']         = 'Qualificació en curs';
$string['bulk_skip_unsupported']       = 'Format no suportat (puja un fitxer vàlid primer)';
$string['bulk_skip_no_proposal']       = 'Sense proposta IA (usa Qualifica amb IA primer)';
$string['bulk_skip_unknown_state']     = 'Estat desconegut a la fila';
$string['bulk_skip_unknown_action']    = 'Acció desconeguda';
$string['bulk_skip_advanced_grading']  = 'La tasca fa servir rúbrica o guia d\'avaluació (qualifica-la al qualificador de Moodle)';

// -----------------------------------------------------------------------.
// Status counter + filter chips (manage page banner).
// -----------------------------------------------------------------------.

$string['count_total']             = '{$a} lliuraments';
$string['count_ai_proposed']       = '{$a} amb proposta IA';
$string['count_teacher_reviewed']  = '{$a} revisades';
$string['count_published']         = '{$a} publicades';
$string['count_problems']          = '{$a} amb problemes';
$string['count_none']              = '{$a} sense qualificar amb IA';
$string['count_filter_to']         = 'Filtra: {$a}';
$string['count_clear_filter']      = 'Mostra-les totes';
$string['count_no_rows_match_filter'] = 'No hi ha lliuraments en aquest estat. Treu el filtre per veure la resta.';
$string['count_perpage_label']        = 'Mostra per pàgina:';
$string['count_perpage_all']          = 'Totes';

// -----------------------------------------------------------------------.
// Extraction (dispatcher.php) — motius pels quals un fitxer o lliurament
// va ser saltat.
// -----------------------------------------------------------------------.

$string['extract_skip_marker']            = 'no suportat';
$string['extract_needs_review_preamble']  = 'Tots els fitxers enviats són il·legibles. Formats suportats: .txt, .md, .docx, .ipynb, .pdf (≤5 MB, amb text extraïble), .zip i fitxers de codi.';
$string['extract_skipped_list']           = 'Saltats: {$a}.';

$string['extract_reason_docx_malformed']     = 'docx (no s\'ha pogut extreure; el fitxer podria estar malmès)';
$string['extract_reason_ipynb_parse']        = 'ipynb (no s\'ha pogut parsejar el JSON)';
$string['extract_reason_pdf_too_large']      = 'pdf massa gran ({$a->actual} MB; màxim {$a->max} MB — vegeu el README del connector)';
$string['extract_reason_pdf_no_text']        = 'pdf sense text extraïble (pot ser un escaneig només-imatge o contingut malmès)';
$string['extract_reason_zip_empty']          = 'zip (buit o només conté fitxers no suportats)';
$string['extract_reason_no_extension']       = 'fitxer sense extensió';
$string['extract_reason_unknown_extension']  = 'extensió no suportada: {$a}';
$string['extract_truncation_warning']        = '{$a->filename} truncat a {$a->chars} caràcters';

// Confirmació inline en tornar a qualificar una fila ja publicada.
$string['confirm_regrade_published'] = 'Aquest lliurament ja està publicat. Tornar a qualificar amb IA? La nota actual al quadern de qualificacions no canviarà, però l\'estat tornarà a «Proposta IA» fins que tornis a aprovar.';
