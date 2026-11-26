{{-- In-app integration guide (ChatGPT, Claude, Claude Code, Cursor, VS Code) — French, tenant URL auto-filled. --}}
@php
    $mcpUrl = $endpoint;
    $mcpName = $displayName ?? 'ClicFlow';
    $slug = \Illuminate\Support\Str::slug(str_replace(' - ClicFlow', '', $mcpName)) ?: 'clicflow';
    $slug = $slug === 'clicflow' ? 'clicflow' : $slug.'-clicflow';
    $j = fn ($v) => json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $snippets = [
        'claude_code' => 'claude mcp add --transport http '.$slug.' '.$mcpUrl,
        'cursor' => $j(['mcpServers' => [$slug => ['url' => $mcpUrl]]]),
        'vscode' => $j(['servers' => [$slug => ['type' => 'http', 'url' => $mcpUrl]]]),
    ];
    $tabs = [
        'chatgpt' => ['label' => 'ChatGPT', 'icon' => 'comments', 'tone' => 'emerald'],
        'claude' => ['label' => 'Claude (web & Desktop)', 'icon' => 'wand-magic-sparkles', 'tone' => 'orange'],
        'claude_code' => ['label' => 'Claude Code', 'icon' => 'code', 'tone' => 'amber'],
        'cursor' => ['label' => 'Cursor', 'icon' => 'sliders', 'tone' => 'slate'],
        'vscode' => ['label' => 'VS Code', 'icon' => 'file-lines', 'tone' => 'sky'],
        'help' => ['label' => 'Dépannage', 'icon' => 'life-ring', 'tone' => 'rose'],
    ];
    $trouble = [
        ['ChatGPT : pas de bouton « Ajouter un serveur MCP personnalisé »', 'Votre abonnement ou vos droits dans l\'espace ChatGPT ne le permettent pas (voir l\'encadré abonnement). Utilisez chatgpt.com sur ordinateur.'],
        ['ChatGPT affiche un autre nom (ex. « ddd »)', 'ChatGPT affiche le nom saisi dans le champ « Nom » du connecteur. Modifiez-le dans ChatGPT (Plugins → ClicFlow → Modifier) ou recréez le connecteur avec le nom « '.$mcpName.' ».'],
        ['« Application inconnue ou adresse de retour non autorisée »', 'Supprimez le connecteur dans l\'assistant et recréez-le. Ne remplissez pas Client ID / Client secret.'],
        ['« Ce compte n\'appartient pas à cet espace ClicFlow »', 'Vous êtes connecté avec le compte d\'un autre espace : cliquez « Changer de compte », ou utilisez l\'adresse MCP de votre propre espace.'],
        ['« Seul un administrateur de l\'espace peut autoriser… »', 'Connectez-vous avec un compte Administrateur de l\'espace.'],
        ['« This tool requires the write scope »', 'La connexion est en lecture seule : révoquez-la ci-dessous et reconnectez en cochant « Écriture ».'],
        ['Erreur 401 / « non autorisé » après plusieurs semaines', 'Connexion révoquée ou inutilisée depuis plus de 60 jours : reconnectez-vous depuis l\'assistant.'],
        ['Trop de requêtes (429)', 'Limite de 60 appels par minute et par connexion : patientez une minute.'],
    ];
@endphp

<div class="rounded-2xl border border-slate-200 bg-white" data-mcp-guide
    x-data="{ tab: 'chatgpt', copied: null, copy(text, key) { navigator.clipboard?.writeText(text); this.copied = key; setTimeout(() => { if (this.copied === key) this.copied = null }, 1800) } }">
    <div class="border-b border-slate-100 p-4 sm:p-5">
        <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-900">
            <x-cf-icon name="book" variant="tile" tone="violet" size="md" />
            Guide : connecter un assistant IA
        </h2>
        <p class="mt-1 text-sm text-slate-600">ChatGPT, Claude, Cursor et VS Code se connectent avec <strong>OAuth</strong> : collez l'adresse ci-dessous, puis cliquez <strong>« Autoriser »</strong> sur la page ClicFlow. Aucune clé à copier.</p>

        <div class="mt-4 grid gap-2 sm:grid-cols-2">
            @foreach ([['k' => 'url', 'label' => 'Adresse du serveur MCP', 'value' => $mcpUrl, 'icon' => 'link', 'tone' => 'sky'], ['k' => 'name', 'label' => 'Nom conseillé', 'value' => $mcpName, 'icon' => 'key', 'tone' => 'orange']] as $row)
                <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50/70 px-3 py-2">
                    <x-cf-icon :name="$row['icon']" size="sm" :tone="$row['tone']" class="shrink-0" />
                    <div class="min-w-0 flex-1">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">{{ $row['label'] }}</p>
                        <p class="truncate font-mono text-xs font-semibold text-slate-900" data-guide-{{ $row['k'] }}>{{ $row['value'] }}</p>
                    </div>
                    <button type="button" class="inline-flex shrink-0 items-center gap-1 rounded-lg bg-[#EA580C] px-2.5 py-1.5 text-[11px] font-semibold text-white hover:bg-[#c2410c]"
                        @click="copy(@js($row['value']), '{{ $row['k'] }}')">
                        <x-cf-icon name="copy" size="sm" class="text-white" />
                        <span x-show="copied !== '{{ $row['k'] }}'">Copier</span>
                        <span x-show="copied === '{{ $row['k'] }}'" x-cloak>Copié</span>
                    </button>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Tabs --}}
    <div class="flex gap-1 overflow-x-auto border-b border-slate-100 px-3 pt-3 sm:px-4" role="tablist">
        @foreach ($tabs as $key => $t)
            <button type="button" role="tab" @click="tab = '{{ $key }}'"
                :class="tab === '{{ $key }}' ? 'border-[#EA580C] text-[#EA580C] bg-orange-50/60' : 'border-transparent text-slate-600 hover:text-slate-900'"
                class="inline-flex shrink-0 items-center gap-1.5 rounded-t-lg border-b-2 px-3 py-2 text-xs font-semibold">
                <x-cf-icon :name="$t['icon']" size="sm" :tone="$t['tone']" />
                {{ $t['label'] }}
            </button>
        @endforeach
    </div>

    <div class="p-4 text-sm leading-relaxed text-slate-700 sm:p-5">
        {{-- ChatGPT --}}
        <div x-show="tab === 'chatgpt'" data-guide-tab="chatgpt">
            <ol class="list-decimal space-y-2 pl-5">
                <li>Sur <strong>chatgpt.com</strong> (ordinateur), ouvrez <strong>Plugins</strong>, cliquez <strong>+</strong> puis <strong>Ajouter un serveur MCP personnalisé</strong>.</li>
                <li>Remplissez les champs :</li>
            </ol>
            <div class="mt-3 overflow-hidden rounded-xl border border-slate-200">
                <table class="w-full text-left text-xs">
                    <tbody class="divide-y divide-slate-100">
                        <tr><th class="w-1/3 bg-slate-50 px-3 py-2 font-semibold text-slate-600">Nom</th><td class="px-3 py-2"><code class="font-semibold text-slate-900">{{ $mcpName }}</code> <span class="text-slate-500">— c'est ce nom que ChatGPT affiche</span></td></tr>
                        <tr><th class="bg-slate-50 px-3 py-2 font-semibold text-slate-600">Description</th><td class="px-3 py-2">Facultatif, ex. « CRM, devis et factures »</td></tr>
                        <tr><th class="bg-slate-50 px-3 py-2 font-semibold text-slate-600">URL du serveur</th><td class="px-3 py-2"><code class="break-all text-slate-900">{{ $mcpUrl }}</code></td></tr>
                        <tr><th class="bg-slate-50 px-3 py-2 font-semibold text-slate-600">Authentification</th><td class="px-3 py-2"><strong>OAuth</strong></td></tr>
                        <tr><th class="bg-slate-50 px-3 py-2 font-semibold text-slate-600">Paramètres avancés OAuth</th><td class="px-3 py-2"><strong class="text-emerald-700">Tout laisser vide</strong> (Client ID, Client secret, URLs, portées). ClicFlow s'enregistre automatiquement.<br><span class="text-slate-500">Si un champ est obligatoire : méthode <code>none</code>, portées <code>mcp:read mcp:write</code>, enregistrement <code>DCR</code>.</span></td></tr>
                    </tbody>
                </table>
            </div>
            <ol start="3" class="mt-3 list-decimal space-y-2 pl-5">
                <li>Cochez <strong>« Je comprends et je veux continuer »</strong>, puis <strong>Créer en tant que plugin</strong>.</li>
                <li>La page ClicFlow s'ouvre : vérifiez l'espace, cochez Lecture / Écriture, cliquez <strong>Autoriser</strong>.</li>
                <li>Installez le plugin, puis dans une conversation tapez <code>{{ '@'.$mcpName }}</code> : « liste mes dernières factures »…</li>
            </ol>
            <p class="mt-4 flex items-start gap-2 rounded-xl border border-amber-200/80 bg-amber-50/80 px-3 py-2.5 text-xs text-amber-900">
                <x-cf-icon name="warning" size="sm" tone="amber" class="mt-0.5 shrink-0" />
                <span><strong>Abonnement ChatGPT :</strong> d'après OpenAI, lecture et écriture sur <strong>Business, Enterprise et Edu</strong> ; <strong>Pro</strong> en lecture seule ; Plus / Go / Gratuit non listés. Si le menu n'apparaît pas, votre formule ne le permet pas.</span>
            </p>
        </div>

        {{-- Claude --}}
        <div x-show="tab === 'claude'" x-cloak data-guide-tab="claude">
            <ol class="list-decimal space-y-2 pl-5">
                <li><strong>claude.ai</strong> → <strong>Paramètres</strong> → <strong>Connecteurs</strong> → <strong>Ajouter un connecteur personnalisé</strong>.</li>
                <li><strong>Nom</strong> : <code class="font-semibold">{{ $mcpName }}</code> — <strong>URL</strong> : <code class="break-all">{{ $mcpUrl }}</code>.</li>
                <li><strong>Paramètres avancés</strong> (OAuth Client ID / Secret) : <strong class="text-emerald-700">laisser vide</strong>.</li>
                <li><strong>Ajouter</strong> → <strong>Se connecter</strong> → page ClicFlow → <strong>Autoriser</strong>.</li>
                <li>Dans une conversation : bouton <strong>+ / Outils</strong> → activez le connecteur.</li>
            </ol>
            <p class="mt-3 text-xs text-slate-500"><strong>Claude Desktop</strong> (Mac / Windows) reprend automatiquement les connecteurs ajoutés sur claude.ai. Sur Team / Enterprise, un propriétaire de l'organisation ajoute le connecteur.</p>
        </div>

        {{-- Claude Code --}}
        <div x-show="tab === 'claude_code'" x-cloak data-guide-tab="claude_code">
            <p>Dans un terminal :</p>
            @include('settings.partials.mcp-guide-snippet', ['key' => 'claude_code', 'code' => $snippets['claude_code']])
            <p class="mt-3">Puis dans Claude Code : <code>/mcp</code> → <strong>{{ $slug }}</strong> → <strong>Authenticate</strong> → page ClicFlow → <strong>Autoriser</strong>.</p>
        </div>

        {{-- Cursor --}}
        <div x-show="tab === 'cursor'" x-cloak data-guide-tab="cursor">
            <p>Dans <code>~/.cursor/mcp.json</code> (ou Paramètres → MCP → Ajouter) :</p>
            @include('settings.partials.mcp-guide-snippet', ['key' => 'cursor', 'code' => $snippets['cursor']])
            <p class="mt-3">Cursor affiche <strong>« Needs login »</strong> : cliquez → page ClicFlow → <strong>Autoriser</strong>.</p>
        </div>

        {{-- VS Code --}}
        <div x-show="tab === 'vscode'" x-cloak data-guide-tab="vscode">
            <p>Dans <code>.vscode/mcp.json</code> (ou commande <strong>MCP: Add Server</strong> → HTTP) :</p>
            @include('settings.partials.mcp-guide-snippet', ['key' => 'vscode', 'code' => $snippets['vscode']])
            <p class="mt-3">Cliquez <strong>Start</strong> au-dessus du serveur → VS Code propose de s'authentifier → page ClicFlow → <strong>Autoriser</strong>.</p>
        </div>

        {{-- Troubleshooting --}}
        <div x-show="tab === 'help'" x-cloak data-guide-tab="help" class="space-y-2">
            @foreach ($trouble as [$q, $a])
                <details class="group rounded-xl border border-slate-200 bg-white px-3 py-2">
                    <summary class="flex cursor-pointer list-none items-center gap-2 text-xs font-semibold text-slate-900">
                        <x-cf-icon name="chevron-right" size="sm" class="text-slate-400 transition group-open:rotate-90" />
                        {{ $q }}
                    </summary>
                    <p class="mt-1.5 pl-6 text-xs text-slate-600">{{ $a }}</p>
                </details>
            @endforeach
        </div>

        <p class="mt-5 flex items-start gap-2 border-t border-slate-100 pt-4 text-xs text-slate-500">
            <x-cf-icon name="shield-halved" size="sm" tone="emerald" class="mt-0.5 shrink-0" />
            <span>Chaque connexion n'accède qu'à cet espace. Les factures et devis créés par l'IA restent en brouillon. Les connexions OAuth apparaissent ci-dessous avec le badge <strong>OAuth</strong> et se révoquent comme une clé. Les clés <code>cfm_…</code> restent disponibles pour les outils qui les demandent.</span>
        </p>
    </div>
</div>
