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
 * Brazilian Portuguese language strings for AI Grader Pro.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'AI Grader Pro';

// Descrições de capabilities (vistas em Administração do site > Permissões).
$string['aigrader:use'] = 'Usar avaliação assistida por IA em tarefas';
$string['aigrader:configure'] = 'Configurar AI Grader Pro em uma tarefa';
$string['aigrader:viewlog'] = 'Ver registro de auditoria do AI Grader Pro';

// Página de configuração do administrador.
$string['setting_enabled'] = 'Habilitar plugin';
$string['setting_enabled_desc'] = 'Interruptor global do AI Grader Pro. Quando desativado, os professores não podem iniciar novas avaliações por IA em nenhuma tarefa. Os registros de auditoria existentes são preservados.';

$string['setting_rubric_autoimport'] = 'Importar automaticamente da rubrica de avaliação';
$string['setting_rubric_autoimport_desc'] = 'Quando uma tarefa usa o método de avaliação por rubrica do Moodle, pré-preenche automaticamente os critérios de avaliação do AI Grader Pro com o conteúdo da rubrica. O professor pode editar os critérios importados antes de habilitar a avaliação por IA.';

$string['setting_default_system_prompt'] = 'Prompt de sistema padrão';
$string['setting_default_system_prompt_desc'] = 'Instrução institucional opcional que é adicionada ao system prompt de cada solicitação de avaliação. Útil para impor tom ou política consistentes entre todos os professores. Exemplo: "Forneça feedback construtivo em registro acadêmico, máximo 200 palavras." Deixe em branco para usar apenas o system prompt padrão do plugin.';

// Formulário de edição da tarefa (mod_assign).
$string['form_enabled'] = 'Habilitar avaliação assistida por IA nesta tarefa';
$string['form_enabled_help'] = 'Quando marcado, os professores podem executar o AI Grader Pro sobre os envios desta tarefa. A IA propõe nota e feedback; o professor revisa e decide. Nada é publicado ao aluno até que o professor aprove.';

$string['form_criteria'] = 'Critérios de avaliação';
$string['form_criteria_help'] = 'Descrição em linguagem natural de como a IA deve avaliar os envios desta tarefa. Escreva as mesmas instruções que daria a um monitor. Mencione os critérios concretos, seu peso relativo e o tom de feedback que você quer. Exemplo:

Avalie esta redação (800-1000 palavras) sobre digitalização educacional segundo estes critérios:
- Clareza da tese (25%): a posição é defensável?
- Qualidade das evidências (30%): as fontes são acadêmicas e bem citadas?
- Estrutura (25%): introdução, desenvolvimento, conclusão
- Linguagem (20%): registro acadêmico, ortografia

Tom: construtivo e específico, em português.';

$string['form_criteria_imported_notice'] = 'Critérios pré-preenchidos a partir da rubrica configurada em "Avaliação > Avaliação avançada". Você pode editá-los antes de habilitar a avaliação por IA.';


$string['form_language_override'] = 'Idioma do feedback (opcional)';
$string['form_language_override_help'] = 'Se definido, o feedback da IA para esta tarefa será neste idioma em vez do idioma do curso. Deixe em "Auto" para usar o idioma do curso.';

$string['form_lang_auto'] = 'Auto (usar idioma do curso)';

// Erros de validação.
$string['error_criteria_required'] = 'Os critérios de avaliação são obrigatórios quando a avaliação assistida por IA está habilitada. Descreva como a IA deve avaliar os envios.';

// Importador de rubricas.
$string['rubric_export_header'] = 'Critérios (importados automaticamente da rubrica de avaliação avançada da tarefa):';

// Tarefas adhoc (vistas em Administração do site > Servidor > Tarefas).
$string['task_grade_submission'] = 'AI Grader Pro: avaliar um envio';
$string['errortaskfailed'] = 'A tarefa de avaliação do AI Grader Pro falhou: {$a}';

// Página de gestão (/local/aigrader/manage.php).
$string['manage_pagetitle']         = 'AI Grader Pro · {$a}';
$string['manage_heading']           = 'AI Grader Pro: {$a}';
$string['manage_disabled']          = 'AI Grader Pro não está habilitado nesta tarefa. Edite a configuração da tarefa para ativá-lo.';
$string['manage_no_submissions']    = 'Ainda não há envios para esta tarefa.';
$string['manage_polling']           = 'Uma avaliação está em andamento. Esta página será atualizada automaticamente.';
$string['manage_back_to_assignment'] = '← Voltar à tarefa';
$string['msg_enqueued']             = 'Tarefa de avaliação por IA enfileirada. Será executada no próximo cron.';
$string['msg_graded_now']           = 'Avaliação por IA concluída. Clique em Revisar para ver a proposta.';
$string['msg_needs_manual_review']  = 'A IA não pôde processar este envio automaticamente. Clique em Revisar para avaliar manualmente.';

$string['th_student']   = 'Aluno';
$string['th_submitted'] = 'Enviado';
$string['th_status']    = 'Estado IA';
$string['th_grade']     = 'Nota proposta';
$string['th_action']    = 'Ação';

$string['btn_grade_with_ai']   = 'Avaliar com IA';
$string['btn_pending']         = 'Processando...';

$string['status_none']        = 'Sem avaliação por IA';
$string['status_pending']     = 'Pendente';
$string['status_proposed']    = 'Proposta IA';
$string['status_reviewed']    = 'Revisada pelo professor';
$string['status_published']   = 'Publicada';
$string['status_error']       = 'Erro';
$string['status_unsupported'] = 'Formato não suportado';

$string['errornotenabled']  = 'AI Grader Pro não está habilitado nesta tarefa.';
$string['errornocriteria']  = 'Não há critérios de avaliação definidos para esta tarefa.';

// Página de revisão (/local/aigrader/review.php).
$string['review_pagetitle']       = 'Revisar proposta IA · {$a}';
$string['review_heading']         = 'Revisar proposta IA: {$a->assign} — {$a->student}';
$string['review_submission_text'] = 'Envio do aluno';
$string['review_proposed']        = 'Nota e feedback propostos (editáveis)';
$string['review_criterion_scores'] = 'Pontuação por critério (da IA, informativa)';
$string['review_proposed_at']     = 'Proposta feita em {$a}';
$string['review_proposed_by']     = 'por {$a}';
$string['manualfallback_banner']  = 'A avaliação por IA não estava disponível para este envio, portanto o formulário está vazio. Preencha nota e feedback manualmente; "Aprovar e publicar" os grava no livro de notas da mesma forma que com as propostas IA. Motivo:';
$string['manualfallback_default'] = 'não há proposta IA registrada para este envio.';
$string['review_submission_files']        = 'Arquivos anexados';
$string['review_submission_seen_by_ai']   = 'Envio como a IA o viu';
$string['review_seen_by_ai_help']         = 'Esta é a versão que a IA leu do arquivo do aluno. Se a proposta IA disser algo estranho, verifique aqui qual texto ela recebeu de fato. Alguns formatos não são processados (PDFs muito grandes, imagens).';
$string['review_seen_by_ai_size']         = '{$a} KB de texto extraídos.';
$string['review_seen_by_ai_warnings']     = 'Avisos sobre a extração:';

$string['field_finalgrade']         = 'Nota final';
$string['field_finalgrade_points']  = 'Nota final (máximo {$a})';
$string['field_finalgrade_reference'] = 'Nota de referência (0-10, não publicada)';
$string['review_advanced_grading']  = 'Esta tarefa usa avaliação avançada ({$a}). O AI Grader Pro ainda não consegue preenchê-la, então não pode publicar a nota daqui. Use a proposta como referência e conclua a avaliação no avaliador do Moodle. Você ainda pode salvar suas alterações aqui sem publicar.';
$string['review_open_grader']       = 'Abrir o avaliador do Moodle para este aluno';
$string['review_grade_none']        = 'Esta tarefa está configurada sem nota: apenas o feedback será publicado para o aluno.';
$string['field_strengths']          = 'Pontos fortes';
$string['field_strengths_hint']     = 'Um por linha. Será mostrado ao aluno como feedback positivo.';
$string['field_improvements']       = 'A melhorar';
$string['field_improvements_hint']  = 'Um por linha. Sugestões construtivas que o aluno verá.';
$string['field_justification']      = 'Justificativa (visível para o aluno)';

$string['btn_review']          = 'Revisar';
$string['btn_view_published']  = 'Ver ✓';
$string['btn_approve_publish'] = 'Aprovar e publicar';
$string['btn_save_draft']      = 'Salvar sem publicar';

$string['msg_published']      = 'Nota aprovada e publicada no livro de notas.';
$string['msg_saved_draft']    = 'Salvo sem publicar. A nota ainda não está no livro de notas.';

$string['feedback_strengths']    = 'Pontos fortes';
$string['feedback_improvements'] = 'A melhorar';
$string['feedback_justification'] = 'Resumo';

$string['errornoproposal']      = 'Não há proposta IA disponível para este envio.';
$string['errorparseproposal']   = 'A proposta IA armazenada não pôde ser lida. Reavalie para regenerá-la.';
$string['errorgradeoutofrange'] = 'A nota deve estar entre {$a->min} e {$a->max} (recebido: {$a->value}).';
$string['erroradvancedgrading'] = 'Esta tarefa usa avaliação avançada (rubrica ou guia de avaliação), então a nota não pode ser publicada pelo AI Grader Pro. Avalie-a no avaliador do Moodle.';

// Strings do Privacy provider.
$string['privacy:metadata'] = 'AI Grader Pro armazena propostas de avaliação geradas por IA, registros de auditoria de cada ação e configuração por tarefa. Também envia dados pessoais a um provedor LLM externo via o AI Subsystem do Moodle.';

// Tabela local_aigrader_assign.
$string['privacy:metadata:assign']               = 'Configuração do AI Grader Pro por tarefa (em quais tarefas está habilitado, critérios de avaliação e overrides). Armazena o id do professor que editou a configuração pela última vez.';
$string['privacy:metadata:assign:assignid']      = 'Id interno da tarefa.';
$string['privacy:metadata:assign:criteria_text'] = 'Critérios de avaliação escritos pelo professor em linguagem natural.';
$string['privacy:metadata:assign:usermodified']  = 'Id do professor que editou a configuração pela última vez. É anonimizado ao excluir o usuário.';
$string['privacy:metadata:assign:timecreated']   = 'Momento em que a configuração foi salva pela primeira vez.';
$string['privacy:metadata:assign:timemodified']  = 'Momento da última modificação.';

// Tabela local_aigrader_submission.
$string['privacy:metadata:submission']                   = 'Estado da avaliação por IA por envio: nota e feedback propostos, além da nota e feedback finais aprovados pelo professor.';
$string['privacy:metadata:submission:submissionid']      = 'Id do envio da tarefa a que se refere.';
$string['privacy:metadata:submission:studentid']         = 'Id do aluno cujo envio foi avaliado.';
$string['privacy:metadata:submission:status']            = 'Estado atual na máquina de estados (pending_ai / ai_proposed / teacher_reviewed / published / error).';
$string['privacy:metadata:submission:proposed_grade']    = 'Nota proposta pela IA (0-10).';
$string['privacy:metadata:submission:proposed_feedback'] = 'Resposta completa do LLM: pontuações por critério, pontos fortes, a melhorar, justificativa.';
$string['privacy:metadata:submission:final_grade']       = 'Nota aprovada pelo professor (pode diferir da proposta se o professor editou).';
$string['privacy:metadata:submission:final_feedback']    = 'Feedback aprovado pelo professor e exibido ao aluno.';
$string['privacy:metadata:submission:final_grader']      = 'Id do professor que aprovou a nota. É anonimizado ao excluir o usuário.';
$string['privacy:metadata:submission:timecreated']       = 'Momento em que a primeira avaliação por IA foi enfileirada.';
$string['privacy:metadata:submission:timemodified']      = 'Momento da última modificação.';
$string['privacy:metadata:submission:timeprocessed']     = 'Momento em que a chamada ao LLM foi concluída.';
$string['privacy:metadata:submission:timepublished']     = 'Momento em que o professor aprovou e a nota foi gravada no livro de notas.';

// Tabela local_aigrader_log.
$string['privacy:metadata:log']                = 'Registro append-only de cada ação de avaliação por IA. Exigido pelo AI Act (Reg. 2024/1689 Anexo III) para sistemas de IA de alto risco em educação.';
$string['privacy:metadata:log:userid']         = 'Id do professor que disparou a ação. É anonimizado ao excluir o usuário.';
$string['privacy:metadata:log:studentid']      = 'Id do aluno cujo envio foi processado.';
$string['privacy:metadata:log:action']         = 'Tipo de ação registrada (grade, regrade, edit, approve, reject).';
$string['privacy:metadata:log:llm_provider']   = 'Nome do provedor LLM utilizado (ex.: openai, azureai).';
$string['privacy:metadata:log:llm_model']      = 'Identificador do modelo LLM utilizado (ex.: llama-3.3-70b-versatile).';
$string['privacy:metadata:log:prompt_text']    = 'Prompt completo enviado ao LLM (inclui o texto do envio do aluno).';
$string['privacy:metadata:log:response_json']  = 'Resposta bruta do LLM em JSON (inclui nota proposta e feedback).';
$string['privacy:metadata:log:tokens_input']   = 'Número de tokens de entrada consumidos pela chamada ao LLM.';
$string['privacy:metadata:log:tokens_output']  = 'Número de tokens de saída consumidos pela chamada ao LLM.';
$string['privacy:metadata:log:proposed_grade'] = 'Nota proposta pelo LLM no momento da ação.';
$string['privacy:metadata:log:final_grade']    = 'Nota final após revisão do professor (se aplicável).';
$string['privacy:metadata:log:teacher_edits']  = 'JSON diff que mostra como o professor modificou a proposta IA.';
$string['privacy:metadata:log:timecreated']    = 'Momento em que a ação foi registrada.';

// Provedor LLM externo (dados transferidos para fora do Moodle).
$string['privacy:metadata:ai_subsystem']             = 'AI Grader Pro envia o texto do envio do aluno juntamente com os critérios de avaliação do professor ao provedor LLM configurado no AI Subsystem do Moodle. O provedor pode estar hospedado dentro ou fora da UE, conforme a escolha da instituição. O administrador do site assina um Data Processing Agreement (DPA) com o provedor escolhido.';
$string['privacy:metadata:ai_subsystem:prompt_text'] = 'Texto do envio do aluno juntamente com os critérios e instruções de avaliação do professor.';
$string['privacy:metadata:ai_subsystem:userid']      = 'Identificador de usuário passado ao provedor LLM para rate-limiting e prevenção de abuso (aplica a política de privacidade do provedor).';

// Banner de erros classificados (apenas professor, nunca ao aluno).
$string['err_banner_title']         = 'A avaliação por IA falhou';
$string['err_banner_title_plural']  = 'A avaliação por IA falhou em {$a} envios';
$string['err_banner_affecting']     = 'Afeta a: {$a}';
$string['err_banner_show_details']  = 'Ver erro técnico';
$string['err_banner_retry']         = 'Tentar novamente agora';

// Payload muito grande.
$string['err_payload_too_large_headline'] = 'O envio excede o limite do modelo';
$string['err_payload_too_large_body']     = 'O envio ocupa {$a->requested} tokens, mas o modelo configurado "{$a->model}" só aceita {$a->limit} tokens por minuto no plano atual.';
$string['err_payload_too_large_body_partial'] = 'O envio superou o limite de tokens por minuto do modelo configurado.';
$string['err_payload_too_large_action']   = 'Mude para um modelo com limite TPM maior em Administração do site → IA → Provedores, ou peça ao aluno para remover os outputs do notebook antes de reenviar.';

// Não autorizado.
$string['err_unauthorized_headline'] = 'O provedor rejeitou a API key';
$string['err_unauthorized_body']     = 'O provedor LLM retornou um erro de autenticação. A API key não existe, é inválida ou foi revogada.';
$string['err_unauthorized_action']   = 'Vá em Administração do site → IA → Provedores e revise a API key do provedor ativo.';

// Rate limit.
$string['err_rate_limited_headline'] = 'Limite de requisições por minuto excedido';
$string['err_rate_limited_body']     = 'Muitas solicitações de avaliação foram enviadas em pouco tempo. O Moodle tentará novamente de forma automática com backoff exponencial.';
$string['err_rate_limited_action']   = 'Não há nada a fazer. A avaliação será retomada quando a cota for liberada.';

// Erro 5xx do provedor.
$string['err_provider_error_headline'] = 'Erro temporário do provedor';
$string['err_provider_error_body']     = 'O provedor LLM retornou um erro de servidor temporário. O Moodle tentará novamente de forma automática.';
$string['err_provider_error_action']   = 'Não há nada a fazer. Se o problema persistir mais de 15 minutos, verifique a página de status do provedor.';

// Erro de rede.
$string['err_network_error_headline'] = 'Não foi possível conectar ao provedor LLM';
$string['err_network_error_body']     = 'A conexão com o provedor LLM falhou (timeout, erro de DNS ou conexão recusada).';
$string['err_network_error_action']   = 'Verifique a conectividade de rede do site e a URL do endpoint do provedor. O Moodle tentará novamente de forma automática.';

// Erro de parsing.
$string['err_parse_error_headline'] = 'O LLM retornou uma resposta inválida';
$string['err_parse_error_body']     = 'O modelo produziu uma saída que não pôde ser parseada para o formato JSON de avaliação esperado.';
$string['err_parse_error_action']   = 'Clique em "Tentar novamente agora" para chamar o modelo novamente. Se o problema persistir, os critérios podem estar incentivando respostas em prosa livre; revise os critérios de avaliação.';

// Desconhecido (catch-all).
$string['err_unknown_headline'] = 'A avaliação por IA falhou';
$string['err_unknown_body']     = 'O provedor retornou um erro: {$a}';
$string['err_unknown_action']   = 'Consulte os detalhes no audit log e tente novamente.';

// -----------------------------------------------------------------------.
// Bulk actions (manage.php: "Com selecionadas..." selector + bulk.php).
// -----------------------------------------------------------------------.

// Barra de ação.
$string['bulk_label_with_selected'] = 'Com selecionadas:';
$string['bulk_apply']               = 'Aplicar';
$string['bulk_select_all']          = 'Selecionar todas as linhas';
$string['bulk_select_row']          = 'Selecionar envio de {$a}';

// Opções do seletor.
$string['bulk_action_choose']          = '-- Escolha uma ação --';
$string['bulk_action_approve_publish'] = 'Publicar nota proposta';
$string['bulk_action_grade_ai']        = 'Avaliar com IA';

// Avisos exibidos na tela de confirmação.
$string['bulk_warning_approve_publish'] = 'Você vai publicar as notas propostas pela IA tal como estão, sem editar. As notas serão gravadas no livro de notas e o aluno será notificado conforme a configuração da tarefa. Esta ação não pode ser desfeita em lote.';
$string['bulk_warning_grade_ai']        = 'Você vai executar a IA sobre os envios selecionados. Se algum já tiver proposta, ela será sobrescrita pela nova. Cada envio consome tokens do provedor configurado.';

// Botões de confirmação.
$string['bulk_confirm_button_approve_publish'] = 'Sim, publicar';
$string['bulk_confirm_button_grade_ai']        = 'Sim, avaliar';

// Tela de confirmação.
$string['bulk_confirm_pagetitle']       = 'AI Grader Pro · Confirmar ação';
$string['bulk_confirm_count']           = 'envios serão processados.';
$string['bulk_confirm_skipped_header']  = 'Serão pulados:';

// Mensagens de erro / validação.
$string['bulk_no_selection']            = 'Você não selecionou nenhum envio.';
$string['errorinvalidaction']           = 'Ação em lote inválida: {$a}';

// Resumo pós-execução (toast de redirect).
$string['bulk_done_ok']                 = '{$a} envios processados';
$string['bulk_done_queued']             = '{$a} envios em fila (o cron completará)';
$string['bulk_done_skipped']            = '{$a} pulados';
$string['bulk_done_errors']             = '{$a} com erro';

// Motivos pelos quais uma linha é pulada (mapeados a skip:<reason>).
$string['bulk_skip_already_published'] = 'Já publicadas';
$string['bulk_skip_in_flight']         = 'Avaliação em andamento';
$string['bulk_skip_unsupported']       = 'Formato não suportado (envie um arquivo válido primeiro)';
$string['bulk_skip_no_proposal']       = 'Sem proposta IA (use Avaliar com IA primeiro)';
$string['bulk_skip_unknown_state']     = 'Estado desconhecido na linha';
$string['bulk_skip_unknown_action']    = 'Ação desconhecida';
$string['bulk_skip_advanced_grading']  = 'A tarefa usa rubrica ou guia de avaliação (avalie no avaliador do Moodle)';

// -----------------------------------------------------------------------.
// Status counter + filter chips (manage page banner).
// -----------------------------------------------------------------------.

$string['count_total']             = '{$a} envios';
$string['count_ai_proposed']       = '{$a} com proposta IA';
$string['count_teacher_reviewed']  = '{$a} revisadas';
$string['count_published']         = '{$a} publicadas';
$string['count_problems']          = '{$a} com problemas';
$string['count_none']              = '{$a} sem avaliação por IA';
$string['count_filter_to']         = 'Filtrar: {$a}';
$string['count_clear_filter']      = 'Mostrar todas';
$string['count_no_rows_match_filter'] = 'Não há envios neste estado. Remova o filtro para ver o resto.';
$string['count_perpage_label']        = 'Mostrar por página:';
$string['count_perpage_all']          = 'Todas';

// -----------------------------------------------------------------------.
// Extraction (dispatcher.php) — motivos pelos quais um arquivo ou envio
// foi pulado.
// -----------------------------------------------------------------------.

$string['extract_skip_marker']            = 'não suportado';
$string['extract_needs_review_preamble']  = 'Todos os arquivos enviados são ilegíveis. Formatos suportados: .txt, .md, .docx, .ipynb, .pdf (≤5 MB, com texto extraível), .zip e arquivos de código.';
$string['extract_skipped_list']           = 'Pulados: {$a}.';

$string['extract_reason_docx_malformed']     = 'docx (não foi possível extrair; o arquivo pode estar corrompido)';
$string['extract_reason_ipynb_parse']        = 'ipynb (não foi possível parsear o JSON)';
$string['extract_reason_pdf_too_large']      = 'pdf muito grande ({$a->actual} MB; máximo {$a->max} MB — ver README do plugin)';
$string['extract_reason_pdf_no_text']        = 'pdf sem texto extraível (pode ser um escaneamento somente-imagem ou conteúdo corrompido)';
$string['extract_reason_zip_empty']          = 'zip (vazio ou contém apenas arquivos não suportados)';
$string['extract_reason_no_extension']       = 'arquivo sem extensão';
$string['extract_reason_unknown_extension']  = 'extensão não suportada: {$a}';
$string['extract_truncation_warning']        = '{$a->filename} truncado em {$a->chars} caracteres';

// Confirmação inline ao reavaliar uma linha já publicada.
$string['confirm_regrade_published'] = 'Este envio já está publicado. Reavaliar com IA? A nota atual no livro de notas não será alterada, mas o estado voltará a "Proposta IA" até que você aprove novamente.';
