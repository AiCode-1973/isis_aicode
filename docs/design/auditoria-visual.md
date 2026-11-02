# Auditoria visual · ÍSIS 1.3.0

Data: 02/11/2026. Base: telas atuais (imagens do manual em `public/assets/img/manual/`), navegação local e leitura do `public/assets/css/app.css`.

## Resumo

A base é boa. Há uma paleta coerente (azul-marinho e verde-azulado), variáveis de cor, componentes reutilizáveis, contraste adequado na maior parte das telas e uma versão para celular funcional. O sistema já parece um produto sério.

O que mais pesa hoje:

1. **Página inicial sem produto à vista:** não mostra o sistema, tem metade do topo vazia e nenhuma prova de confiança. É a vitrine de vendas e é o ponto mais fraco.
2. **Painel que começa por uma parede de alertas:** 11 linhas sublinhadas antes dos números. O gestor não vê de cara "como estou".
3. **A fonte varia de computador para computador:** o CSS pede a fonte Inter, mas ela não é carregada.
4. **Pequenos defeitos que passam ar de inacabado:** item de menu cortado, textos com "(ões)" e "(s)", títulos repetidos, selos que quebram em duas linhas.

Nada exige trocar de tecnologia. Tudo se resolve no CSS e nos templates PHP atuais.

---

## Prioridade 1 · Defeitos (corrigir já) · **concluída na 1.4.0 e 1.4.1**

| # | Problema | Onde | Proposta | Esforço |
|---|---|---|---|---|
| 1.1 | **Fonte inconsistente.** O CSS declara "Inter", mas não há `@font-face`, então só quem tem a Inter instalada a vê. Nos clientes, a fonte vira Segoe UI ou Roboto e muda alturas, quebras de linha e o "peso" da marca. | Todo o sistema | Hospedar a Inter (woff2, pesos 400, 600 e 700 ou a variável) em `public/assets/fonts/` com `font-display: swap`. A política de segurança (CSP `'self'`) permite. | Baixo |
| 1.2 | **Item de menu cortado.** "Relatórios de impacto (RIPD)" ultrapassa a largura do menu e encosta na borda, no computador e no celular. | Menu lateral | Encurtar para "RIPD" ou "Relatórios de impacto" e garantir `overflow: hidden; text-overflow: ellipsis` nos itens. | Baixo |
| 1.3 | **Plurais com parênteses:** "1 solicitação(ões)", "1 dia(s)", "fornecedor(es)". São 88 ocorrências em 25 arquivos e soam como sistema antigo. | Painel, alertas, listas, e-mails | Criar a função `plural(n, 'solicitação', 'solicitações')` e trocar todas as ocorrências. | Médio |
| 1.4 | **Selo quebrando em duas linhas:** "Dados de saúde (prontuário, exames, diagnósticos)" vira um selo vermelho de duas linhas e "sensíveis" encosta no nome. | Detalhe da operação, inventário | Mostrar a categoria como texto e o selo "sensível" curto ao lado, ou permitir quebra com cantos menos arredondados. | Baixo |
| 1.5 | **Título repetido:** a barra do topo repete o título da página logo acima do H1 (ex.: "Riscos" e "Riscos"). | Todas as telas internas | Usar a barra para o caminho (Conformidade › Riscos) ou para a busca e os atalhos, sem repetir o título. | Baixo |

---

## Prioridade 2 · Maior impacto

### 2.1 Página inicial (vendas)

**Hoje:**
- O topo tem título e botões à esquerda e a metade direita vazia.
- Não mostra nenhuma tela do produto.
- Os módulos aparecem como 9 cartões iguais e não incluem RIPD nem a verificação em duas etapas.
- Não há números, depoimentos, perguntas frequentes nem selo de confiança.
- Os selos "LGPD · Governança…" parecem botões, mas não são clicáveis.
- O cabeçalho só tem "Entrar" e "Criar conta", sem navegação entre as seções.

**Proposta:**
- **Topo:** à direita, uma imagem real do painel numa moldura de navegador (com dados fictícios), levemente inclinada e com sombra. Abaixo dos botões, uma linha de confiança: "Teste grátis de 14 dias · Sem cartão · Dados isolados por empresa".
- **Faixa "Para quem":** quatro ícones (empresas, hospitais, clínicas, laboratórios), alinhada ao vídeo da Ísis.
- **"Como o ÍSIS ajuda":** 3 ou 4 blocos alternando texto e imagem real: inventário e RIPD, titulares e incidentes com prazos, painel e relatório para a diretoria, segurança.
- **Módulos:** cartões compactos com todos os módulos, incluindo RIPD e duas etapas.
- **Segurança como argumento de venda:** criptografia, duas etapas, trilha de auditoria com verificação de integridade e servidor no Brasil, se for o caso.
- **Perguntas frequentes** (5 ou 6), antes dos planos.
- **Planos** (já existe) e uma chamada final com o botão de cadastro.
- **Cabeçalho:** links âncora "Módulos · Segurança · Planos · Dúvidas" (sem voltar o link de preços para o topo como item solto).
- **Espaço para o vídeo da Ísis**, quando estiver pronto.

### 2.2 Painel de conformidade

**Hoje:**
- Abre com 11 alertas sublinhados, em vermelho e amarelo misturados, que no celular ocupam a tela inteira.
- Os 8 indicadores só aparecem depois.
- Não há visão de evolução nem um "índice" geral.

**Proposta:**
- **Primeira dobra = resposta à pergunta "como estou?":**
  - um cartão com o índice de maturidade (ex.: 72%, calculado com o que o painel já mede: operações completas, riscos tratados, prazos em dia, treinamentos);
  - os 4 indicadores principais;
  - a mini matriz de riscos.
- **Alertas agrupados por gravidade:** "Urgente (3)", "Atenção (8)", com no máximo 3 visíveis e "ver todos". O ícone e a cor indicam o tipo, sem sublinhado em tudo.
- **Gráficos simples em SVG gerado no servidor**, sem biblioteca: solicitações por mês, ações concluídas e vencidas. Isso também serve ao relatório de conformidade.
- **No celular:** índice e indicadores primeiro, alertas recolhidos.

### 2.3 Tabelas e listas

**Hoje:**
- Muitas colunas quebram em duas ou três linhas ("Recepção e Atendimento", nomes, "6 dia(s) de atraso").
- Os filtros ocupam um cartão alto antes da lista.

**Proposta:**
- Larguras mínimas por tipo de coluna; o texto secundário vai para uma segunda linha cinza, dentro da coluna principal.
- Filtros numa barra compacta de uma linha, com o botão "Filtros" recolhível no celular.
- Destaque da linha ao passar o mouse e a linha inteira clicável.
- Paginação ou "carregar mais" nas listas longas.

---

## Prioridade 3 · Acabamento

| # | Proposta |
|---|---|
| 3.1 | **Escala tipográfica:** hoje h1 tem 1,6rem, h2 1,15rem e o corpo 15px, muito próximos. Use 1,75 / 1,25 / 1rem, rótulos 0,75rem e números dos indicadores maiores (2rem), com algarismos de largura fixa. |
| 3.2 | **Indicadores:** ícone e número alinhados no topo e texto auxiliar numa linha só (ex.: "6 abertos · 4 altos"), sem quebras como "1 de risco alto". |
| 3.3 | **Botões:** padronizar alturas (36 e 44px) e deixar uma única ação primária por tela. No painel, "Operação / Risco / Comunicar incidente / Convidar" viram um botão "Novo ▾". |
| 3.4 | **Estados vazios** com ilustração leve e um próximo passo claro, em vez de só texto. |
| 3.5 | **Telas de entrada e cadastro:** no cadastro, o painel azul tem um grande vazio no alto. Alinhar o conteúdo ao centro e incluir um destaque, como uma tela pequena do produto ou "14 dias grátis". |
| 3.6 | **Selos de situação:** uma paleta única por significado (andamento, concluído, atraso, rascunho), já que hoje "Em revisão" e "não definida" usam o mesmo amarelo para coisas diferentes. |
| 3.7 | **Microinterações:** transição curta em cartões e linhas, foco visível mais elegante e retorno visual ao salvar (já existe o "Salvando..."). |
| 3.8 | **Ícones:** revisar os que destoam do conjunto (o de incidentes parece uma luminária) e criar ícones próprios para RIPD e duas etapas. |
| 3.9 | **Modo escuro (opcional):** as variáveis de cor já permitem. Pode ficar para depois. |

---

## O que está bom e deve ser mantido

- A paleta azul-marinho e verde-azulado: sóbria, combina com o tema e passa confiança.
- O menu lateral organizado por seções e recolhível.
- A matriz de riscos, clara e com legenda.
- As páginas de detalhe com conteúdo principal e coluna lateral ("Pontos de atenção", "Responsabilidade").
- As telas de entrada divididas, com mensagem de valor.
- O CSS próprio e sem dependências: rápido e compatível com a política de segurança.

---

## Plano sugerido

| Etapa | Conteúdo | Resultado |
|---|---|---|
| A | Prioridade 1 (fonte, menu, plurais, selos, título repetido) | Sistema com acabamento profissional em todas as telas |
| B | Nova página inicial (2.1) | Vitrine de vendas com o produto à mostra |
| C | Painel novo (2.2) com índice de maturidade e gráficos | Valor percebido no primeiro acesso |
| D | Tabelas, tipografia e componentes (2.3 e prioridade 3) | Uso diário mais leve |

Cada etapa é conferida no navegador, no computador e no celular, com as imagens do manual atualizadas no final.

**O que ajudaria a calibrar a etapa B:** de 2 a 3 sites de referência que você goste, confirmação do logo e das cores oficiais, se há clientes ou números que possam aparecer como prova de confiança, e se o servidor fica no Brasil (vale como argumento de segurança).
