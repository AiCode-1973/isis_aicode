<?php

declare(strict_types=1);

namespace Isis\Services;

/**
 * Modelos iniciais de políticas e de treinamento. São pontos de partida para adaptação:
 * devem ser revisados pela empresa e por sua assessoria jurídica antes de aprovados.
 */
final class Templates
{
    private const string NOTICE = "[MODELO PARA ADAPTAÇÃO: revise com a assessoria jurídica e ajuste à realidade da empresa antes de aprovar.]\n\n";

    /** @return list<array{title: string, category: string, review_months: int, content: string}> */
    public static function policies(string $company): array
    {
        return [
            [
                'title' => 'Política de Privacidade e Proteção de Dados Pessoais',
                'category' => 'privacidade_interna',
                'review_months' => 12,
                'content' => self::NOTICE . <<<TXT
1. OBJETIVO
Estabelecer as diretrizes de {$company} para o tratamento de dados pessoais, em conformidade com a Lei nº 13.709/2018 (LGPD).

2. ABRANGÊNCIA
Aplica-se a todos os colaboradores, prestadores de serviço e parceiros que tratem dados pessoais em nome da empresa.

3. PRINCÍPIOS
Todo tratamento deve observar finalidade, adequação, necessidade, livre acesso, qualidade dos dados, transparência, segurança, prevenção, não discriminação e responsabilização (art. 6º).

4. REGRAS GERAIS
4.1 Trate apenas os dados necessários para a finalidade informada.
4.2 Todo tratamento deve ter hipótese legal definida e estar registrado no inventário de dados.
4.3 Dados sensíveis e de crianças e adolescentes exigem cuidado redobrado e autorização do encarregado para novos usos.
4.4 Não compartilhe dados pessoais com terceiros sem contrato que preveja obrigações de proteção de dados.
4.5 Guarde os dados apenas pelo prazo definido e elimine-os de forma segura ao fim.

5. DIREITOS DOS TITULARES
Pedidos de titulares devem ser encaminhados imediatamente ao encarregado pelo canal oficial, para resposta dentro do prazo.

6. INCIDENTES
Qualquer suspeita de incidente com dados pessoais deve ser comunicada imediatamente pelo ÍSIS (Incidentes de segurança) ou ao encarregado.

7. ENCARREGADO
O encarregado de dados é o ponto de contato com titulares e com a ANPD. Dúvidas sobre esta política devem ser enviadas a ele.

8. DESCUMPRIMENTO
O descumprimento desta política sujeita o responsável às medidas disciplinares e contratuais cabíveis.
TXT,
            ],
            [
                'title' => 'Política de Segurança da Informação',
                'category' => 'seguranca',
                'review_months' => 12,
                'content' => self::NOTICE . <<<TXT
1. OBJETIVO
Proteger a confidencialidade, a integridade e a disponibilidade das informações de {$company}.

2. CONTROLE DE ACESSO
2.1 Cada pessoa usa credenciais individuais e intransferíveis.
2.2 O acesso é concedido pelo menor privilégio necessário e revisado periodicamente.
2.3 Acessos de quem deixa a empresa são revogados no mesmo dia.

3. SENHAS E AUTENTICAÇÃO
Use senhas fortes e únicas e, sempre que disponível, autenticação em dois fatores.

4. EQUIPAMENTOS E DOCUMENTOS
4.1 Bloqueie a tela ao se ausentar.
4.2 Não deixe documentos com dados pessoais expostos (política de mesa limpa).
4.3 Use apenas ferramentas e aplicativos autorizados para dados da empresa.

5. CÓPIAS DE SEGURANÇA
Os dados críticos têm cópias de segurança periódicas e testadas.

6. E-MAIL E PHISHING
Desconfie de mensagens que pedem senhas, pagamentos ou dados. Na dúvida, não clique e comunique.

7. INCIDENTES
Comunique imediatamente qualquer suspeita de incidente pelo ÍSIS.
TXT,
            ],
            [
                'title' => 'Procedimento de Resposta a Incidentes com Dados Pessoais',
                'category' => 'incidentes',
                'review_months' => 12,
                'content' => self::NOTICE . <<<TXT
1. COMUNICAÇÃO INTERNA
Quem identificar um incidente comunica imediatamente pelo ÍSIS ou ao encarregado, sem apagar evidências.

2. CONTENÇÃO
O encarregado e a área de tecnologia adotam as medidas para interromper o incidente e reduzir seus efeitos.

3. AVALIAÇÃO
Avaliam-se os dados envolvidos, a quantidade de titulares, a possibilidade de identificação e os danos possíveis, para concluir se há risco ou dano relevante aos titulares.

4. COMUNICAÇÃO À ANPD E AOS TITULARES
Havendo risco ou dano relevante, a comunicação à ANPD e aos titulares é feita no prazo regulamentar (Resolução CD/ANPD nº 15/2024), com as informações do art. 48, § 1º, da LGPD.

5. REGISTRO E MELHORIA
Todo incidente é registrado com as providências tomadas, a causa e as lições aprendidas, gerando ações corretivas.
TXT,
            ],
        ];
    }

    /** @return array{title: string, summary: string, content: string, duration_minutes: int, questions: list<array{question: string, options: list<string>, correct: int}>} */
    public static function lgpdTraining(): array
    {
        return [
            'title' => 'Fundamentos da LGPD para colaboradores',
            'summary' => 'O que são dados pessoais, os princípios da LGPD, os direitos dos titulares e como agir no dia a dia.',
            'duration_minutes' => 20,
            'content' => <<<TXT
1. O QUE É A LGPD
A Lei Geral de Proteção de Dados (Lei nº 13.709/2018) define regras para o uso de dados pessoais por empresas e órgãos públicos. O objetivo é proteger a liberdade e a privacidade das pessoas.

2. DADO PESSOAL E DADO SENSÍVEL
Dado pessoal é qualquer informação que identifica ou pode identificar uma pessoa: nome, CPF, e-mail, telefone, endereço, imagem, placa do carro.
Dado sensível exige cuidado maior: saúde, origem racial ou étnica, religião, opinião política, filiação sindical, vida sexual, dados genéticos ou biométricos.

3. PRINCÍPIOS NO DIA A DIA
• Use os dados só para a finalidade informada à pessoa.
• Colete apenas o necessário.
• Mantenha os dados corretos e atualizados.
• Proteja os dados contra acessos indevidos e perdas.

4. DIREITOS DOS TITULARES
As pessoas podem pedir confirmação, acesso, correção, eliminação, portabilidade, informações sobre compartilhamento e revogar o consentimento. Se receber um pedido assim, encaminhe imediatamente ao encarregado: há prazo para responder.

5. CUIDADOS PRÁTICOS
• Não compartilhe senhas e bloqueie a tela ao sair.
• Não envie dados pessoais por aplicativos ou e-mails pessoais.
• Confira o destinatário antes de enviar arquivos.
• Não deixe documentos expostos e descarte-os de forma segura.
• Desconfie de mensagens que pedem dados ou senhas.

6. INCIDENTES
Vazou um dado, enviou um arquivo para a pessoa errada ou perdeu um documento? Comunique na hora pelo ÍSIS. Comunicar rápido reduz os danos e ajuda a empresa a cumprir os prazos legais.
TXT,
            'questions' => [
                ['question' => 'Qual destas informações é um dado pessoal sensível?', 'options' => ['E-mail corporativo', 'Resultado de exame médico', 'Número do pedido', 'Cidade da filial'], 'correct' => 1],
                ['question' => 'Um cliente pede para saber quais dados a empresa tem sobre ele. O que você faz?', 'options' => ['Ignora, pois não é sua função', 'Responde com o que lembrar', 'Encaminha imediatamente ao encarregado', 'Pede que ele procure a ANPD'], 'correct' => 2],
                ['question' => 'Você enviou uma planilha com dados de clientes para o e-mail errado. Qual a atitude correta?', 'options' => ['Apagar o e-mail enviado e não comentar', 'Comunicar o incidente imediatamente pelo ÍSIS', 'Esperar para ver se alguém reclama', 'Enviar de novo para o destinatário certo e esquecer'], 'correct' => 1],
                ['question' => 'Segundo o princípio da necessidade, a empresa deve:', 'options' => ['Coletar o máximo de dados possível', 'Coletar apenas os dados necessários à finalidade', 'Guardar os dados para sempre', 'Compartilhar dados com parceiros livremente'], 'correct' => 1],
                ['question' => 'Qual prática ajuda a proteger dados pessoais no dia a dia?', 'options' => ['Usar a mesma senha em todos os sistemas', 'Bloquear a tela ao se ausentar', 'Enviar documentos pelo WhatsApp pessoal', 'Deixar fichas sobre a mesa'], 'correct' => 1],
            ],
        ];
    }
}
