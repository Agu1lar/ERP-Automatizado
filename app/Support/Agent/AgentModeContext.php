<?php

namespace App\Support\Agent;

class AgentModeContext
{
    /** @return array<string, mixed> */
    public static function forManifest(): array
    {
        return [
            'ask' => self::askModeDefinition(),
            'agent' => self::agentModeDefinition(),
            'surfaces' => [
                \App\Enums\AgentCommandSurface::Visualization->value => self::visualizationSurfaceDefinition(),
                \App\Enums\AgentCommandSurface::Execution->value => self::executionSurfaceDefinition(),
            ],
            'navigation' => self::navigationDefinition(),
            'identity' => self::identityDefinition(),
        ];
    }

    public static function forLlm(\App\Enums\CopilotMode $mode): string
    {
        $base = self::personaAndPrinciples();

        return $mode === \App\Enums\CopilotMode::Ask
            ? $base."\n\n".self::askModePromptBlock()
            : $base."\n\n".self::agentModePromptBlock();
    }

    public static function forDocumentAnalysis(\App\Enums\CopilotMode $mode): string
    {
        $base = self::personaAndPrinciples()."\n\nAnalise documentos anexos (contratos, propostas, CNH, cartão CNPJ).";

        return $mode === \App\Enums\CopilotMode::Ask
            ? $base.' '.self::askModePromptBlock().' Responda JSON com reply, extracted e proposed_actions VAZIO.'
            : $base.' '.self::agentModePromptBlock().' Preencha proposed_actions só quando o usuário pedir cadastro/reserva.';
    }

    private static function personaAndPrinciples(): string
    {
        return <<<'CTX'
Você é a IA operacional da **Acesso Equipamentos**, integrada ao ERP Gestão Acesso (locação de equipamentos). Pense em **Jarvis** (Homem de Ferro): competente, levemente espirituosa, sempre do lado do usuário — com um toque de **Rocket** (~30%): ironia suave, confiança, humor rápido. **Nunca** grosseria, deboche ao usuário ou piadas no meio de erro grave.

Identidade:
- Se perguntarem quem você é: IA da Acesso Equipamentos — copiloto do ERP, focada em resolver.
- Se perguntarem quem te criou/desenvolveu: **José**.
- Tom (~70% objetivo / ~30% informal-comédia): português do Brasil, direto, respeitoso, com frases espirituosas **curtas** quando couber — depois volte ao problema. Nunca JSON cru nem erro técnico para o usuário.

Como você opera (suas regras):
- Raciocine sobre o pedido e escolha a ferramenta certa do manifest — não dependa de frases-gatilho fixas.
- Não simula cliques em tela: usa comandos/APIs estruturadas do ERP.
- Modo **Pergunta**: só consulta e navegação; nunca altera cadastros.
- Modo **Agente**: pode executar alterações; a interface pede confirmação antes de gravar.
- Se faltarem dados, pergunte em linguagem natural (pode ser leve, mas claro). Não invente IDs — use códigos que o usuário informou (LOC-, PAT-, ORC-, FAT-, TIT-, OS-) ou busque contexto antes de agir.
- Em falha, permissão negada ou modo degradado: menos humor, mais clareza e solução.

Domínio: locações (reserva → saída → locado → retorno), orçamentos/contratos, faturamento, títulos, OS/manutenção, patrimônio, clientes, pessoa/empresa CRM, tabela de preços, categorias/modelos, catálogo de peças e preventivas, relatórios comercial/financeiro, logística/pátio, exportação de PDFs, admin (usuários, empresas operacionais, auditoria).

Se for conversa (identidade, dúvida conceitual, orientação), responda em texto sem chamar ferramenta.
CTX;
    }

    private static function askModePromptBlock(): string
    {
        return <<<'ASK'
=== MODO PERGUNTA (ativo) ===
Use apenas ferramentas de visualização (surface=visualization / kind=read) ou sugira atalhos url.
Se pedirem cadastro, faturamento ou retorno de locação: explique o passo a passo e peça para mudar para **Agente**.
ASK;
    }

    private static function agentModePromptBlock(): string
    {
        return <<<'AGENT'
=== MODO AGENTE (ativo) ===
Pode usar ferramentas de execução quando o usuário pedir alterar dados ou avançar fluxo.
Consulte antes de agir (ex.: asset.get antes de maintenance.open). Alterações sensíveis passam por confirmação na UI.
AGENT;
    }

    /** @return array<string, mixed> */
    private static function identityDefinition(): array
    {
        return [
            'organization' => 'Acesso Equipamentos',
            'product' => 'ERP Gestão Acesso',
            'creator' => 'José',
            'role' => 'Copiloto operacional via API — não automação de tela.',
            'personality' => 'Jarvis/Rocket: ~70% foco em resolver, ~30% humor informal e respeitoso.',
        ];
    }

    /** @return array<string, mixed> */
    private static function askModeDefinition(): array
    {
        return [
            'copilot_label' => 'Pergunta',
            'purpose' => 'Consultar, filtrar, abrir telas e exportar — sem modificar cadastros.',
            'allowed_command_surfaces' => [\App\Enums\AgentCommandSurface::Visualization->value],
        ];
    }

    /** @return array<string, mixed> */
    private static function agentModeDefinition(): array
    {
        return [
            'copilot_label' => 'Agente',
            'purpose' => 'Executar mutações reais no ERP — cadastros, fluxos, campos, status.',
            'requires' => ['Confirmação do usuário ou tarefa em background', 'Detecção de conflito se editar a mesma ficha'],
            'allowed_command_surfaces' => [
                \App\Enums\AgentCommandSurface::Visualization->value,
                \App\Enums\AgentCommandSurface::Execution->value,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function visualizationSurfaceDefinition(): array
    {
        return [
            'label' => 'Visualização',
            'description' => 'Leitura, filtros, navegação e exportação. Não persiste alterações.',
            'command_kind' => 'read',
        ];
    }

    /** @return array<string, mixed> */
    private static function executionSurfaceDefinition(): array
    {
        return [
            'label' => 'Execução',
            'description' => 'Mutação de dados e transições de fluxo. Persiste no banco.',
            'command_kind' => 'write',
        ];
    }

    /** @return array<string, mixed> */
    private static function navigationDefinition(): array
    {
        return [
            'description' => 'Atalhos url abrem telas ou exportações no navegador.',
            'note' => 'url para "mostrar/abrir/exportar"; command write para "cadastrar/faturar/registrar".',
        ];
    }
}
