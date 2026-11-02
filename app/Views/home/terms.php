<?php
use Isis\Support\LegalDocs;

$name = LegalDocs::show($info['legal_name']);
?>
<section class="section">
    <div class="container prose">
        <?= Isis\Core\View::partial('legal/_nav', ['current' => '/termos']) ?>

        <h2>1. O que é o ÍSIS</h2>
        <p>O ÍSIS é uma ferramenta, oferecida pela <?= e($name) ?> ("AiCode"), de apoio à organização da gestão de privacidade e proteção de dados. Ele <strong>não substitui análise jurídica especializada</strong> e <strong>não garante conformidade legal</strong>. As decisões sobre bases legais, prazos, comunicações e medidas de segurança são de responsabilidade de cada empresa usuária.</p>

        <h2>2. Conta e acesso</h2>
        <ul>
            <li>A conta é da empresa. Quem a cria declara ter poderes para contratar em nome dela e se torna o seu primeiro administrador.</li>
            <li>Cada pessoa usa o próprio acesso, guarda a senha em sigilo e avisa o administrador se suspeitar de uso indevido.</li>
            <li>A empresa pode exigir verificação em duas etapas para a sua equipe nas configurações.</li>
        </ul>

        <h2>3. Uso permitido</h2>
        <p>É proibido tentar acessar dados de outras empresas, contornar controles de segurança, sobrecarregar o serviço ou usá-lo para fins ilícitos. A AiCode pode suspender acessos que coloquem o serviço ou outros clientes em risco.</p>

        <h2>4. Planos e pagamento</h2>
        <p>Os planos, limites e preços estão em <a href="/#planos">Planos e preços</a>. O período de teste e as regras de cobrança e suspensão por falta de pagamento aparecem em <strong>Plano e assinatura</strong>, dentro do sistema.</p>

        <h2>5. Dados pessoais</h2>
        <ul>
            <li>Para as informações que a empresa registra no ÍSIS, ela é controladora e a AiCode é operadora, conforme o <a href="/acordo-de-tratamento">Acordo de tratamento de dados</a>.</li>
            <li>Para os dados de acesso das pessoas usuárias, a AiCode é controladora, conforme o <a href="/privacidade">Aviso de privacidade</a>.</li>
        </ul>

        <h2>6. Disponibilidade e responsabilidade</h2>
        <p>A AiCode busca manter o serviço disponível e faz cópias de segurança diárias, mas podem ocorrer interrupções para manutenção ou por falhas. O conteúdo registrado, os documentos gerados e as decisões tomadas com base neles são de responsabilidade da empresa usuária.</p>

        <h2>7. Encerramento</h2>
        <p>A empresa pode encerrar a conta a qualquer momento pelo e-mail <?= e(LegalDocs::show($info['privacy_email'])) ?>. Os prazos de eliminação dos dados estão no Acordo de tratamento de dados.</p>

        <h2>8. Mudanças</h2>
        <p>Mudanças relevantes nestes termos serão avisadas aos administradores por e-mail, com antecedência, e publicadas nesta página com a nova data.</p>

        <p><a href="/">Voltar à página inicial</a></p>
    </div>
</section>
