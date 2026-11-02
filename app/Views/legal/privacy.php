<?php
use Isis\Support\LegalDocs;

$name = LegalDocs::show($info['legal_name']);
$email = LegalDocs::show($info['privacy_email']);
?>
<section class="section">
    <div class="container prose">
        <?= Isis\Core\View::partial('legal/_nav', ['current' => '/privacidade']) ?>

        <p>Este aviso explica como a <?= e($name) ?> ("AiCode") trata os dados pessoais de quem usa o ÍSIS e de quem visita este site, conforme a Lei Geral de Proteção de Dados Pessoais (Lei 13.709/2018, LGPD).</p>

        <h2>1. Quem é o controlador</h2>
        <ul>
            <li><strong>Controladora:</strong> <?= e($name) ?>, CNPJ <?= e(LegalDocs::show($info['legal_cnpj'])) ?>, <?= e(LegalDocs::show($info['legal_address'])) ?>.</li>
            <li><strong>Encarregado pelo tratamento de dados (DPO):</strong> <?= e(LegalDocs::show($info['dpo_name'])) ?>, pelo e-mail <?= e($email) ?>.</li>
        </ul>

        <h2>2. A quem este aviso se aplica</h2>
        <p>Ele vale para os dados que a AiCode trata <strong>como controladora</strong>:</p>
        <ul>
            <li>dados das pessoas que acessam o ÍSIS (administradores, encarregados, gestores, colaboradores e auditores das empresas clientes);</li>
            <li>dados de quem cria uma conta ou entra em contato com a AiCode;</li>
            <li>dados técnicos de quem visita este site.</li>
        </ul>
        <p>As informações que cada empresa registra nos módulos do ÍSIS (inventário, solicitações de titulares, incidentes, fornecedores e demais) pertencem a ela, que é a <strong>controladora</strong>. Para esses dados, a AiCode é <strong>operadora</strong> e segue o <a href="/acordo-de-tratamento">Acordo de tratamento de dados</a>. Isso inclui os pedidos enviados pelo canal do titular de cada empresa: dúvidas e pedidos sobre eles devem ser dirigidos à própria empresa.</p>

        <h2>3. Quais dados tratamos, para quê e com qual base legal</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th scope="col">Dados</th><th scope="col">Finalidade</th><th scope="col">Base legal (LGPD, art. 7º)</th></tr></thead>
                <tbody>
                    <tr><td>Nome, e-mail corporativo, cargo, setor e perfil de acesso</td><td>Criar e manter a conta, controlar permissões e enviar e-mails do serviço (confirmação, convites, lembretes de prazos)</td><td>Execução de contrato (inciso V)</td></tr>
                    <tr><td>Senha (guardada só como hash) e chave da verificação em duas etapas (criptografada)</td><td>Autenticar o acesso com segurança</td><td>Execução de contrato (inciso V)</td></tr>
                    <tr><td>Registros de acesso: data, hora, endereço IP e navegador</td><td>Guarda obrigatória de registros de acesso a aplicações</td><td>Cumprimento de obrigação legal (inciso II), Marco Civil da Internet, art. 15</td></tr>
                    <tr><td>Trilha de auditoria: quem fez cada alteração, quando e o que mudou</td><td>Segurança, prevenção a fraudes e prova das ações registradas pela empresa</td><td>Legítimo interesse (inciso IX) e exercício regular de direitos (inciso VI)</td></tr>
                    <tr><td>Tentativas de entrada (resumo criptográfico do e-mail e do IP)</td><td>Bloquear ataques de adivinhação de senha</td><td>Legítimo interesse (inciso IX)</td></tr>
                    <tr><td>Dados da empresa cliente e do responsável pela contratação, faturas e pagamentos</td><td>Cobrança e obrigações fiscais</td><td>Execução de contrato (inciso V) e obrigação legal (inciso II)</td></tr>
                </tbody>
            </table>
        </div>
        <p>A AiCode não vende dados, não usa os dados para publicidade e não toma decisões automatizadas que afetem as pessoas.</p>

        <h2>4. Cookies</h2>
        <p>O ÍSIS usa um único cookie, <span class="mono">isis_sid</span>, estritamente necessário para manter a sessão de quem está conectado. Ele expira ao sair, após 30 minutos sem uso ou no máximo em 8 horas. Não há cookies de publicidade, de análise de audiência nem de terceiros. As fontes e imagens do site são servidas pelo próprio ÍSIS.</p>

        <h2>5. Com quem compartilhamos</h2>
        <ul>
            <li><strong>Prestadores de infraestrutura</strong> (hospedagem e envio de e-mails), contratados como suboperadores, apenas para prestar o serviço. A lista atual está no <a href="/acordo-de-tratamento#suboperadores">Acordo de tratamento de dados</a>.</li>
            <li><strong>A própria empresa cliente:</strong> administradores da empresa veem os dados de conta e a trilha de ações das pessoas da sua equipe.</li>
            <li><strong>Autoridades</strong>, quando houver obrigação legal ou ordem judicial.</li>
        </ul>

        <h2>6. Onde os dados ficam</h2>
        <p>O ÍSIS e o banco de dados são hospedados em servidor localizado no Brasil. Se algum suboperador tratar dados fora do país, isso será informado na lista de suboperadores, com as garantias exigidas pela LGPD (art. 33).</p>

        <h2>7. Por quanto tempo</h2>
        <ul>
            <li><strong>Dados da conta:</strong> enquanto a pessoa tiver acesso ao ÍSIS. Quem é desativado pela empresa deixa de entrar, mas seu nome continua nos registros que fez, para preservar a trilha de auditoria.</li>
            <li><strong>Registros de acesso:</strong> no mínimo 6 meses, como exige o Marco Civil da Internet.</li>
            <li><strong>Trilha de auditoria:</strong> enquanto a conta da empresa estiver ativa.</li>
            <li><strong>Tentativas de entrada:</strong> 2 dias. <strong>Links de convite e de troca de senha:</strong> apagados 30 dias após o uso ou o vencimento. <strong>Registro de lembretes enviados:</strong> 90 dias.</li>
            <li><strong>Cópias de segurança:</strong> criptografadas, guardadas por até 5 semanas (7 diárias e 4 semanais) e então substituídas.</li>
            <li><strong>Encerramento da conta da empresa:</strong> os dados são eliminados em até 30 dias após o pedido, salvo o que precisar ser guardado por obrigação legal ou para o exercício de direitos. As cópias de segurança expiram no ciclo acima.</li>
        </ul>

        <h2>8. Como protegemos</h2>
        <p>Isolamento entre empresas, acesso por perfil, verificação em duas etapas, criptografia dos dados sensíveis e das cópias de segurança, senhas com hash forte, conexão segura (HTTPS), bloqueio de tentativas repetidas e trilha de auditoria com verificação de integridade. Nenhuma medida elimina todo risco: se ocorrer um incidente de segurança que possa causar dano relevante, a AiCode comunicará as pessoas afetadas e a ANPD, conforme o art. 48 da LGPD.</p>

        <h2>9. Seus direitos</h2>
        <p>Você pode pedir confirmação de que tratamos seus dados, acesso, correção, anonimização, bloqueio ou eliminação do que for desnecessário, portabilidade, informação sobre compartilhamentos e revisão do consentimento, quando houver (LGPD, art. 18). Também pode se opor a um tratamento feito com base no legítimo interesse.</p>
        <ul>
            <li>Dados de nome, cargo e senha podem ser alterados por você mesmo em <strong>Meu perfil</strong>.</li>
            <li>Para os demais pedidos, escreva para <?= e($email) ?>. Respondemos em até 15 dias.</li>
            <li>Se o pedido for sobre dados que uma empresa registrou no ÍSIS, encaminharemos a ela, que é quem decide sobre esses dados.</li>
            <li>Você também pode reclamar à Autoridade Nacional de Proteção de Dados (ANPD).</li>
        </ul>

        <h2>10. Mudanças neste aviso</h2>
        <p>Quando o aviso mudar de forma relevante, a nova versão será publicada nesta página com a data de atualização, e os administradores das empresas serão avisados por e-mail.</p>

        <p><a href="/">Voltar à página inicial</a></p>
    </div>
</section>
