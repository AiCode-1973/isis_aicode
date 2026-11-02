<?php
use Isis\Support\LegalDocs;

$name = LegalDocs::show($info['legal_name']);
$email = LegalDocs::show($info['privacy_email']);
$subprocessors = array_values(array_filter(array_map('trim', preg_split('/\R/', $info['subprocessors']) ?: [])));
?>
<section class="section">
    <div class="container prose">
        <?= Isis\Core\View::partial('legal/_nav', ['current' => '/acordo-de-tratamento']) ?>

        <p>Este acordo faz parte do contrato de uso do ÍSIS e regula o tratamento de dados pessoais que a <?= e($name) ?> ("AiCode", <strong>operadora</strong>) realiza em nome de cada empresa cliente ("Cliente", <strong>controladora</strong>), conforme os artigos 37 a 40 e 46 a 49 da LGPD. O administrador que cria a conta o aceita em nome da empresa.</p>

        <h2>1. Objeto</h2>
        <p>A AiCode trata os dados pessoais que o Cliente registra no ÍSIS somente para prestar o serviço contratado: armazenar, organizar, exibir, enviar lembretes, gerar documentos e relatórios, fazer cópias de segurança e dar suporte quando o Cliente pedir.</p>

        <h2>2. Dados e titulares envolvidos</h2>
        <ul>
            <li><strong>Titulares:</strong> pessoas cujos dados o Cliente registra, como empregados, clientes, pacientes, alunos, fornecedores e quem envia pedidos pelo canal do titular.</li>
            <li><strong>Dados:</strong> os que o Cliente decidir registrar, inclusive dados sensíveis quando ele os informar. Nomes e contatos de titulares nos pedidos e incidentes são guardados criptografados.</li>
            <li><strong>Duração:</strong> enquanto durar o contrato, mais o prazo de eliminação da cláusula 8.</li>
        </ul>

        <h2>3. Responsabilidades do Cliente (controlador)</h2>
        <ul>
            <li>Decidir a finalidade e a base legal de cada tratamento e registrar no ÍSIS apenas os dados necessários.</li>
            <li>Atender os titulares e comunicar incidentes à ANPD quando for o caso. O ÍSIS ajuda a organizar essas tarefas, mas a decisão e a responsabilidade são do Cliente.</li>
            <li>Gerenciar quem da sua equipe tem acesso e com qual perfil, e desativar quem sair da empresa.</li>
        </ul>

        <h2>4. Obrigações da AiCode (operadora)</h2>
        <ul>
            <li>Tratar os dados apenas conforme as instruções do Cliente, que são as configurações e ações feitas por ele no ÍSIS e este acordo. Instruções que pareçam contrárias à lei serão informadas ao Cliente.</li>
            <li>Não usar os dados para fins próprios, não vendê-los e não compartilhá-los além do previsto neste acordo.</li>
            <li>Garantir que as pessoas da AiCode com acesso aos dados tenham dever de confidencialidade e acessem apenas o necessário para o suporte e a manutenção.</li>
            <li>Ajudar o Cliente, dentro do que o serviço permite, a atender pedidos de titulares, a elaborar o relatório de impacto e a responder à ANPD.</li>
            <li>Encaminhar ao Cliente, sem responder diretamente, qualquer pedido de titular recebido sobre dados do Cliente.</li>
        </ul>

        <h2>5. Segurança</h2>
        <p>A AiCode mantém as medidas descritas no <a href="/privacidade">Aviso de privacidade</a>: isolamento entre empresas, acesso por perfil, verificação em duas etapas, criptografia de dados sensíveis e das cópias de segurança, conexão segura, controle de tentativas de acesso e trilha de auditoria com verificação de integridade. Elas podem ser melhoradas a qualquer tempo, sem redução do nível de proteção.</p>

        <h2>6. Incidentes de segurança</h2>
        <p>Se a AiCode souber de um incidente que afete dados do Cliente, ela o avisará pelo e-mail do administrador da conta <strong>em até 48 horas</strong>, informando o que se sabe até o momento: natureza do incidente, dados e titulares possivelmente afetados, medidas tomadas e contato para acompanhamento. Isso permite ao Cliente avaliar a comunicação à ANPD e aos titulares no prazo da regulamentação.</p>

        <h2 id="suboperadores">7. Suboperadores</h2>
        <p>O Cliente autoriza a AiCode a contratar os suboperadores abaixo, que ficam sujeitos a obrigações de proteção de dados equivalentes às deste acordo. A inclusão de um novo suboperador será informada com pelo menos 15 dias de antecedência, e o Cliente pode se opor e encerrar o contrato sem multa.</p>
        <?php if ($subprocessors === []): ?>
        <p class="muted"><?= e(LegalDocs::PENDING) ?></p>
        <?php else: ?>
        <ul>
            <?php foreach ($subprocessors as $line): ?>
            <li><?= e($line) ?></li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>

        <h2>8. Fim do contrato</h2>
        <p>Ao encerrar o contrato, o Cliente pode exportar os relatórios e registros disponíveis no ÍSIS. Os dados do Cliente serão eliminados em até 30 dias após o pedido de encerramento, salvo o que a lei obrigar a guardar. As cópias de segurança criptografadas expiram no ciclo normal de até 5 semanas.</p>

        <h2>9. Fiscalização</h2>
        <p>O Cliente pode pedir à AiCode informações que demonstrem o cumprimento deste acordo, como a descrição das medidas de segurança e a lista de suboperadores. A integridade da trilha de auditoria pode ser verificada pelo próprio Cliente no módulo Auditoria.</p>

        <h2>10. Contato</h2>
        <p>Encarregado da AiCode: <?= e(LegalDocs::show($info['dpo_name'])) ?>, <?= e($email) ?>.</p>

        <p><a href="/">Voltar à página inicial</a></p>
    </div>
</section>
