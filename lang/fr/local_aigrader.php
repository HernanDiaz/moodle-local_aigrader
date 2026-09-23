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
 * French language strings for AI Grader Pro.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'AI Grader Pro';

// Descriptions des capabilities (visibles dans Administration du site > Permissions).
$string['aigrader:use'] = 'Utiliser l\'évaluation assistée par IA dans les devoirs';
$string['aigrader:configure'] = 'Configurer AI Grader Pro pour un devoir';
$string['aigrader:viewlog'] = 'Consulter le journal d\'audit d\'AI Grader Pro';

// Page de configuration de l\'administrateur.
$string['setting_enabled'] = 'Activer le plugin';
$string['setting_enabled_desc'] = 'Interrupteur global d\'AI Grader Pro. Lorsqu\'il est désactivé, les enseignants ne peuvent pas lancer de nouvelles évaluations par IA sur aucun devoir. Les journaux d\'audit existants sont conservés.';

$string['setting_rubric_autoimport'] = 'Importer automatiquement depuis la grille d\'évaluation';
$string['setting_rubric_autoimport_desc'] = 'Lorsqu\'un devoir utilise la méthode d\'évaluation par grille de Moodle, pré-remplit automatiquement les critères d\'évaluation d\'AI Grader Pro avec le contenu de la grille. L\'enseignant peut modifier les critères importés avant d\'activer l\'évaluation par IA.';

$string['setting_default_system_prompt'] = 'Prompt système par défaut';
$string['setting_default_system_prompt_desc'] = 'Instruction institutionnelle optionnelle ajoutée au system prompt de chaque demande d\'évaluation. Utile pour imposer un ton ou une politique cohérents entre tous les enseignants. Exemple : « Fournis un feedback constructif dans un registre académique, 200 mots maximum. » Laissez vide pour n\'utiliser que le system prompt par défaut du plugin.';

// Formulaire d\'édition du devoir (mod_assign).
$string['form_enabled'] = 'Activer l\'évaluation assistée par IA sur ce devoir';
$string['form_enabled_help'] = 'Lorsqu\'il est coché, les enseignants peuvent lancer AI Grader Pro sur les remises de ce devoir. L\'IA propose une note et un feedback ; l\'enseignant révise et décide. Rien n\'est publié à l\'étudiant tant que l\'enseignant n\'a pas approuvé.';

$string['form_criteria'] = 'Critères d\'évaluation';
$string['form_criteria_help'] = 'Description en langage naturel de la manière dont l\'IA doit évaluer les remises de ce devoir. Rédigez les mêmes instructions que vous donneriez à un assistant. Mentionnez les critères concrets, leur poids relatif et le ton de feedback que vous souhaitez. Exemple :

Évalue cette dissertation (800-1000 mots) sur la numérisation éducative selon ces critères :
- Clarté de la thèse (25%) : la position est-elle défendable ?
- Qualité des preuves (30%) : les sources sont-elles académiques et bien citées ?
- Structure (25%) : introduction, développement, conclusion
- Langue (20%) : registre académique, orthographe

Ton : constructif et spécifique, en français.';

$string['form_criteria_imported_notice'] = 'Critères pré-remplis depuis la grille configurée dans « Évaluation > Évaluation avancée ». Vous pouvez les modifier avant d\'activer l\'évaluation par IA.';

$string['form_model_override'] = 'Modèle (optionnel)';
$string['form_model_override_help'] = 'Si défini, ce devoir utilise ce modèle spécifique au lieu de celui par défaut du fournisseur IA. Utile lorsque vous voulez un modèle plus performant (ou moins cher) pour un devoir précis. Laissez vide pour utiliser le modèle par défaut global.';

$string['form_language_override'] = 'Langue du feedback (optionnel)';
$string['form_language_override_help'] = 'Si défini, le feedback de l\'IA pour ce devoir sera dans cette langue au lieu de la langue du cours. Laissez sur « Auto » pour utiliser la langue du cours.';

$string['form_lang_auto'] = 'Auto (utiliser la langue du cours)';

// Erreurs de validation.
$string['error_criteria_required'] = 'Les critères d\'évaluation sont obligatoires lorsque l\'évaluation assistée par IA est activée. Décrivez comment l\'IA doit évaluer les remises.';

// Importateur de grilles.
$string['rubric_export_header'] = 'Critères (importés automatiquement de la grille d\'évaluation avancée du devoir) :';

// Tâches adhoc (visibles dans Administration du site > Serveur > Tâches).
$string['task_grade_submission'] = 'AI Grader Pro : évaluer une remise';
$string['errortaskfailed'] = 'La tâche d\'évaluation d\'AI Grader Pro a échoué : {$a}';

// Page de gestion (/local/aigrader/manage.php).
$string['manage_pagetitle']         = 'AI Grader Pro · {$a}';
$string['manage_heading']           = 'AI Grader Pro : {$a}';
$string['manage_disabled']          = 'AI Grader Pro n\'est pas activé sur ce devoir. Modifiez la configuration du devoir pour l\'activer.';
$string['manage_no_submissions']    = 'Il n\'y a pas encore de remises pour ce devoir.';
$string['manage_polling']           = 'Une évaluation est en cours. Cette page se rafraîchira automatiquement.';
$string['manage_back_to_assignment'] = '← Retour au devoir';
$string['msg_enqueued']             = 'Tâche d\'évaluation IA mise en file. Elle s\'exécutera au prochain cron.';
$string['msg_graded_now']           = 'Évaluation IA terminée. Cliquez sur Réviser pour voir la proposition.';
$string['msg_needs_manual_review']  = 'L\'IA n\'a pas pu traiter cette remise automatiquement. Cliquez sur Réviser pour évaluer manuellement.';

$string['th_student']   = 'Étudiant';
$string['th_submitted'] = 'Remis le';
$string['th_status']    = 'Statut IA';
$string['th_grade']     = 'Note proposée';
$string['th_action']    = 'Action';

$string['btn_grade_with_ai']   = 'Évaluer avec l\'IA';
$string['btn_pending']         = 'Traitement...';

$string['status_none']        = 'Sans évaluation IA';
$string['status_pending']     = 'En attente';
$string['status_proposed']    = 'Proposition IA';
$string['status_reviewed']    = 'Révisée par l\'enseignant';
$string['status_published']   = 'Publiée';
$string['status_error']       = 'Erreur';
$string['status_unsupported'] = 'Format non pris en charge';

$string['errornotenabled']  = 'AI Grader Pro n\'est pas activé sur ce devoir.';
$string['errornocriteria']  = 'Aucun critère d\'évaluation n\'est défini pour ce devoir.';

// Page de révision (/local/aigrader/review.php).
$string['review_pagetitle']       = 'Réviser la proposition IA · {$a}';
$string['review_heading']         = 'Réviser la proposition IA : {$a->assign} — {$a->student}';
$string['review_submission_text'] = 'Remise de l\'étudiant';
$string['review_proposed']        = 'Note et feedback proposés (modifiables)';
$string['review_criterion_scores'] = 'Note par critère (de l\'IA, à titre informatif)';
$string['review_proposed_at']     = 'Proposition faite le {$a}';
$string['review_proposed_by']     = 'par {$a}';
$string['manualfallback_banner']  = 'L\'évaluation par IA n\'était pas disponible pour cette remise, le formulaire est donc vide. Remplissez la note et le feedback manuellement ; « Approuver et publier » les écrira au carnet de notes comme pour les propositions IA. Motif :';
$string['manualfallback_default'] = 'aucune proposition IA enregistrée pour cette remise.';
$string['review_submission_files']        = 'Fichiers joints';
$string['review_submission_seen_by_ai']   = 'Remise telle que l\'IA l\'a lue';
$string['review_seen_by_ai_help']         = 'Voici la version que l\'IA a lue du fichier de l\'étudiant. Si la proposition IA dit quelque chose d\'étrange, vérifiez ici quel texte elle a réellement reçu. Certains formats ne sont pas traités (PDFs très volumineux, images).';
$string['review_seen_by_ai_size']         = '{$a} KB de texte extraits.';
$string['review_seen_by_ai_warnings']     = 'Avertissements concernant l\'extraction :';

$string['field_finalgrade']         = 'Note finale';
$string['field_finalgrade_points']  = 'Note finale (sur {$a})';
$string['field_finalgrade_reference'] = 'Note indicative (0-10, non publiée)';
$string['review_advanced_grading']  = 'Ce devoir utilise une évaluation avancée ({$a}). AI Grader Pro ne sait pas encore la remplir, il ne peut donc pas publier la note depuis cette page. Utilisez la proposition comme référence et terminez l\'évaluation dans l\'interface d\'évaluation de Moodle. Vous pouvez toujours enregistrer vos modifications ici sans publier.';
$string['review_open_grader']       = 'Ouvrir l\'interface d\'évaluation de Moodle pour cet étudiant';
$string['review_grade_none']        = 'Ce devoir est configuré sans note : seul le feedback sera publié à l\'étudiant.';
$string['field_strengths']          = 'Points forts';
$string['field_strengths_hint']     = 'Un par ligne. Sera affiché à l\'étudiant comme feedback positif.';
$string['field_improvements']       = 'À améliorer';
$string['field_improvements_hint']  = 'Un par ligne. Suggestions constructives que verra l\'étudiant.';
$string['field_justification']      = 'Justification (visible par l\'étudiant)';

$string['btn_review']          = 'Réviser';
$string['btn_view_published']  = 'Voir ✓';
$string['btn_approve_publish'] = 'Approuver et publier';
$string['btn_save_draft']      = 'Enregistrer sans publier';

$string['msg_published']      = 'Note approuvée et publiée au carnet de notes.';
$string['msg_saved_draft']    = 'Enregistré sans publier. La note n\'est pas encore au carnet de notes.';

$string['feedback_strengths']    = 'Points forts';
$string['feedback_improvements'] = 'À améliorer';
$string['feedback_justification'] = 'Résumé';

$string['errornoproposal']      = 'Aucune proposition IA disponible pour cette remise.';
$string['errorparseproposal']   = 'La proposition IA enregistrée n\'a pas pu être lue. Ré-évaluez pour la régénérer.';
$string['errorgradeoutofrange'] = 'La note doit être comprise entre {$a->min} et {$a->max} (reçu : {$a->value}).';
$string['erroradvancedgrading'] = 'Ce devoir utilise une évaluation avancée (grille ou guide d\'évaluation) : la note ne peut pas être publiée depuis AI Grader Pro. Évaluez-le dans l\'interface d\'évaluation de Moodle.';

// Strings du Privacy provider.
$string['privacy:metadata'] = 'AI Grader Pro stocke les propositions d\'évaluation générées par IA, les journaux d\'audit de chaque action et la configuration par devoir. Il envoie également des données personnelles à un fournisseur LLM externe via l\'AI Subsystem de Moodle.';

// Table local_aigrader_assign.
$string['privacy:metadata:assign']               = 'Configuration d\'AI Grader Pro par devoir (sur quels devoirs il est activé, critères d\'évaluation, et overrides). Stocke l\'id de l\'enseignant qui a édité la configuration en dernier.';
$string['privacy:metadata:assign:assignid']      = 'Id interne du devoir.';
$string['privacy:metadata:assign:criteria_text'] = 'Critères d\'évaluation rédigés par l\'enseignant en langage naturel.';
$string['privacy:metadata:assign:usermodified']  = 'Id de l\'enseignant qui a édité la configuration en dernier. Anonymisé lors de la suppression de l\'utilisateur.';
$string['privacy:metadata:assign:timecreated']   = 'Moment où la configuration a été sauvegardée pour la première fois.';
$string['privacy:metadata:assign:timemodified']  = 'Moment de la dernière modification.';

// Table local_aigrader_submission.
$string['privacy:metadata:submission']                   = 'État de l\'évaluation IA par remise : note et feedback proposés, ainsi que la note et le feedback finaux approuvés par l\'enseignant.';
$string['privacy:metadata:submission:submissionid']      = 'Id de la remise de devoir concernée.';
$string['privacy:metadata:submission:studentid']         = 'Id de l\'étudiant dont la remise a été évaluée.';
$string['privacy:metadata:submission:status']            = 'État actuel dans la machine à états (pending_ai / ai_proposed / teacher_reviewed / published / error).';
$string['privacy:metadata:submission:proposed_grade']    = 'Note proposée par l\'IA (0-10).';
$string['privacy:metadata:submission:proposed_feedback'] = 'Réponse complète du LLM : scores par critère, points forts, à améliorer, justification.';
$string['privacy:metadata:submission:final_grade']       = 'Note approuvée par l\'enseignant (peut différer de la proposition si l\'enseignant a édité).';
$string['privacy:metadata:submission:final_feedback']    = 'Feedback approuvé par l\'enseignant et affiché à l\'étudiant.';
$string['privacy:metadata:submission:final_grader']      = 'Id de l\'enseignant qui a approuvé la note. Anonymisé lors de la suppression de l\'utilisateur.';
$string['privacy:metadata:submission:timecreated']       = 'Moment où la première évaluation IA a été mise en file.';
$string['privacy:metadata:submission:timemodified']      = 'Moment de la dernière modification.';
$string['privacy:metadata:submission:timeprocessed']     = 'Moment où l\'appel au LLM s\'est terminé.';
$string['privacy:metadata:submission:timepublished']     = 'Moment où l\'enseignant a approuvé et la note a été écrite au carnet de notes.';

// Table local_aigrader_log.
$string['privacy:metadata:log']                = 'Journal append-only de chaque action d\'évaluation IA. Exigé par l\'AI Act (Règl. 2024/1689 Annexe III) pour les systèmes IA à haut risque en éducation.';
$string['privacy:metadata:log:userid']         = 'Id de l\'enseignant qui a déclenché l\'action. Anonymisé lors de la suppression de l\'utilisateur.';
$string['privacy:metadata:log:studentid']      = 'Id de l\'étudiant dont la remise a été traitée.';
$string['privacy:metadata:log:action']         = 'Type d\'action enregistrée (grade, regrade, edit, approve, reject).';
$string['privacy:metadata:log:llm_provider']   = 'Nom du fournisseur LLM utilisé (ex. : openai, azureai).';
$string['privacy:metadata:log:llm_model']      = 'Identifiant du modèle LLM utilisé (ex. : llama-3.3-70b-versatile).';
$string['privacy:metadata:log:prompt_text']    = 'Prompt complet envoyé au LLM (inclut le texte de la remise de l\'étudiant).';
$string['privacy:metadata:log:response_json']  = 'Réponse brute du LLM en JSON (inclut la note proposée et le feedback).';
$string['privacy:metadata:log:tokens_input']   = 'Nombre de tokens d\'entrée consommés par l\'appel au LLM.';
$string['privacy:metadata:log:tokens_output']  = 'Nombre de tokens de sortie consommés par l\'appel au LLM.';
$string['privacy:metadata:log:proposed_grade'] = 'Note proposée par le LLM au moment de l\'action.';
$string['privacy:metadata:log:final_grade']    = 'Note finale après révision de l\'enseignant (si applicable).';
$string['privacy:metadata:log:teacher_edits']  = 'JSON diff montrant comment l\'enseignant a modifié la proposition IA.';
$string['privacy:metadata:log:timecreated']    = 'Moment où l\'action a été enregistrée.';

// Fournisseur LLM externe (données transférées hors de Moodle).
$string['privacy:metadata:ai_subsystem']             = 'AI Grader Pro envoie le texte de la remise de l\'étudiant accompagné des critères d\'évaluation de l\'enseignant au fournisseur LLM configuré dans l\'AI Subsystem de Moodle. Le fournisseur peut être hébergé dans ou hors de l\'UE selon le choix de l\'institution. L\'administrateur du site signe un Data Processing Agreement (DPA) avec le fournisseur choisi.';
$string['privacy:metadata:ai_subsystem:prompt_text'] = 'Texte de la remise de l\'étudiant accompagné des critères et instructions d\'évaluation de l\'enseignant.';
$string['privacy:metadata:ai_subsystem:userid']      = 'Identifiant d\'utilisateur transmis au fournisseur LLM pour le rate-limiting et la prévention des abus (la politique de confidentialité du fournisseur s\'applique).';

// Bannière d\'erreurs classifiées (enseignant uniquement, jamais à l\'étudiant).
$string['err_banner_title']         = 'L\'évaluation par IA a échoué';
$string['err_banner_title_plural']  = 'L\'évaluation par IA a échoué pour {$a} remises';
$string['err_banner_affecting']     = 'Concerne : {$a}';
$string['err_banner_show_details']  = 'Voir l\'erreur technique';
$string['err_banner_retry']         = 'Réessayer maintenant';

// Payload trop volumineux.
$string['err_payload_too_large_headline'] = 'La remise dépasse la limite du modèle';
$string['err_payload_too_large_body']     = 'La remise occupe {$a->requested} tokens, mais le modèle configuré « {$a->model} » n\'accepte que {$a->limit} tokens par minute dans le plan actuel.';
$string['err_payload_too_large_body_partial'] = 'La remise a dépassé la limite de tokens par minute du modèle configuré.';
$string['err_payload_too_large_action']   = 'Passez à un modèle avec une limite TPM plus élevée dans Administration du site → IA → Fournisseurs, ou demandez à l\'étudiant de supprimer les outputs du notebook avant de remettre à nouveau.';

// Non autorisé.
$string['err_unauthorized_headline'] = 'Le fournisseur a rejeté la clé API';
$string['err_unauthorized_body']     = 'Le fournisseur LLM a renvoyé une erreur d\'authentification. La clé API n\'existe pas, est invalide ou a été révoquée.';
$string['err_unauthorized_action']   = 'Allez dans Administration du site → IA → Fournisseurs et vérifiez la clé API du fournisseur actif.';

// Rate limit.
$string['err_rate_limited_headline'] = 'Limite de requêtes par minute dépassée';
$string['err_rate_limited_body']     = 'Trop de demandes d\'évaluation ont été envoyées en peu de temps. Moodle réessaiera automatiquement avec un backoff exponentiel.';
$string['err_rate_limited_action']   = 'Rien à faire. L\'évaluation reprendra lorsque le quota sera libéré.';

// Erreur 5xx du fournisseur.
$string['err_provider_error_headline'] = 'Erreur temporaire du fournisseur';
$string['err_provider_error_body']     = 'Le fournisseur LLM a renvoyé une erreur serveur temporaire. Moodle réessaiera automatiquement.';
$string['err_provider_error_action']   = 'Rien à faire. Si le problème persiste plus de 15 minutes, consultez la page de statut du fournisseur.';

// Erreur réseau.
$string['err_network_error_headline'] = 'Impossible de se connecter au fournisseur LLM';
$string['err_network_error_body']     = 'La connexion au fournisseur LLM a échoué (timeout, erreur DNS ou connexion refusée).';
$string['err_network_error_action']   = 'Vérifiez la connectivité réseau du site et l\'URL de l\'endpoint du fournisseur. Moodle réessaiera automatiquement.';

// Erreur de parsing.
$string['err_parse_error_headline'] = 'Le LLM a renvoyé une réponse invalide';
$string['err_parse_error_body']     = 'Le modèle a produit une sortie qui n\'a pas pu être parsée au format JSON d\'évaluation attendu.';
$string['err_parse_error_action']   = 'Cliquez sur « Réessayer maintenant » pour rappeler le modèle. Si le problème persiste, les critères incitent peut-être à des réponses en prose libre ; revoyez les critères d\'évaluation.';

// Inconnu (catch-all).
$string['err_unknown_headline'] = 'L\'évaluation par IA a échoué';
$string['err_unknown_body']     = 'Le fournisseur a renvoyé une erreur : {$a}';
$string['err_unknown_action']   = 'Consultez les détails dans le journal d\'audit et réessayez.';

// -----------------------------------------------------------------------.
// Bulk actions (manage.php : « Avec les sélectionnées… » selector + bulk.php).
// -----------------------------------------------------------------------.

// Barre d\'action.
$string['bulk_label_with_selected'] = 'Avec les sélectionnées :';
$string['bulk_apply']               = 'Appliquer';
$string['bulk_select_all']          = 'Sélectionner toutes les lignes';
$string['bulk_select_row']          = 'Sélectionner la remise de {$a}';

// Options du sélecteur.
$string['bulk_action_choose']          = '-- Choisir une action --';
$string['bulk_action_approve_publish'] = 'Publier la note proposée';
$string['bulk_action_grade_ai']        = 'Évaluer avec l\'IA';

// Avertissements sur la page de confirmation.
$string['bulk_warning_approve_publish'] = 'Vous allez publier les notes proposées par l\'IA telles quelles, sans modification. Les notes seront écrites dans le carnet de notes et les étudiants seront notifiés selon la configuration du devoir. Cette action ne peut pas être annulée en lot.';
$string['bulk_warning_grade_ai']        = 'Vous allez lancer l\'IA sur les remises sélectionnées. Si certaines ont déjà une proposition, elle sera écrasée par la nouvelle. Chaque remise consomme des tokens du fournisseur configuré.';

// Boutons de confirmation.
$string['bulk_confirm_button_approve_publish'] = 'Oui, publier';
$string['bulk_confirm_button_grade_ai']        = 'Oui, évaluer';

// Page de confirmation.
$string['bulk_confirm_pagetitle']       = 'AI Grader Pro · Confirmer l\'action';
$string['bulk_confirm_count']           = 'remises seront traitées.';
$string['bulk_confirm_skipped_header']  = 'Seront ignorées :';

// Messages d\'erreur / validation.
$string['bulk_no_selection']            = 'Vous n\'avez sélectionné aucune remise.';
$string['errorinvalidaction']           = 'Action en lot non valide : {$a}';

// Résumé post-exécution (toast de redirect).
$string['bulk_done_ok']                 = '{$a} remises traitées';
$string['bulk_done_queued']             = '{$a} remises en file (le cron les terminera)';
$string['bulk_done_skipped']            = '{$a} ignorées';
$string['bulk_done_errors']             = '{$a} en erreur';

// Motifs pour lesquels une ligne est ignorée (mappés à skip:<reason>).
$string['bulk_skip_already_published'] = 'Déjà publiées';
$string['bulk_skip_in_flight']         = 'Évaluation en cours';
$string['bulk_skip_unsupported']       = 'Format non pris en charge (envoyez d\'abord un fichier valide)';
$string['bulk_skip_no_proposal']       = 'Sans proposition IA (utilisez d\'abord Évaluer avec l\'IA)';
$string['bulk_skip_unknown_state']     = 'État inconnu de la ligne';
$string['bulk_skip_unknown_action']    = 'Action inconnue';
$string['bulk_skip_advanced_grading']  = 'Le devoir utilise une grille ou un guide d\'évaluation (évaluez-le dans l\'interface d\'évaluation de Moodle)';

// -----------------------------------------------------------------------.
// Status counter + filter chips (manage page banner).
// -----------------------------------------------------------------------.

$string['count_total']             = '{$a} remises';
$string['count_ai_proposed']       = '{$a} avec proposition IA';
$string['count_teacher_reviewed']  = '{$a} révisées';
$string['count_published']         = '{$a} publiées';
$string['count_problems']          = '{$a} avec problèmes';
$string['count_none']              = '{$a} sans évaluation IA';
$string['count_filter_to']         = 'Filtrer : {$a}';
$string['count_clear_filter']      = 'Tout afficher';
$string['count_no_rows_match_filter'] = 'Aucune remise dans cet état. Retirez le filtre pour voir le reste.';
$string['count_perpage_label']        = 'Afficher par page :';
$string['count_perpage_all']          = 'Toutes';

// -----------------------------------------------------------------------.
// Extraction (dispatcher.php) — raisons pour lesquelles un fichier ou
// une remise a été ignoré.
// -----------------------------------------------------------------------.

$string['extract_skip_marker']            = 'non pris en charge';
$string['extract_needs_review_preamble']  = 'Tous les fichiers envoyés sont illisibles. Formats pris en charge : .txt, .md, .docx, .ipynb, .pdf (≤5 MB, avec texte extractible), .zip et fichiers de code.';
$string['extract_skipped_list']           = 'Ignorés : {$a}.';

$string['extract_reason_docx_malformed']     = 'docx (impossible d\'extraire ; le fichier est peut-être endommagé)';
$string['extract_reason_ipynb_parse']        = 'ipynb (impossible de parser le JSON)';
$string['extract_reason_pdf_too_large']      = 'pdf trop volumineux ({$a->actual} MB ; maximum {$a->max} MB — voir le README du plugin)';
$string['extract_reason_pdf_no_text']        = 'pdf sans texte extractible (peut être un scan uniquement-image ou un contenu endommagé)';
$string['extract_reason_zip_empty']          = 'zip (vide ou ne contient que des fichiers non pris en charge)';
$string['extract_reason_no_extension']       = 'fichier sans extension';
$string['extract_reason_unknown_extension']  = 'extension non prise en charge : {$a}';
$string['extract_truncation_warning']        = '{$a->filename} tronqué à {$a->chars} caractères';

// Confirmation inline en réévaluant une ligne déjà publiée.
$string['confirm_regrade_published'] = 'Cette remise est déjà publiée. Réévaluer avec l\'IA ? La note actuelle dans le carnet de notes ne changera pas, mais l\'état reviendra à « Proposition IA » jusqu\'à ce que vous approuviez à nouveau.';
