<?php

namespace App\Services;

use App\Enums\ClientSource;
use App\Enums\ClientStatus;
use App\Enums\InvoiceStatus;
use App\Enums\ProposalStatus;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Proposal;
use App\Models\ProposalItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DataImportService
{
    public const TYPES = ['clients', 'invoices', 'proposals', 'products', 'prospects'];

    public function __construct(
        private ProspectService $csv,
        private ClientCodeService $clientCodes,
        private InvoiceTotalsService $totals,
        private InvoiceReferenceService $invoiceRefs,
        private ProposalReferenceService $proposalRefs,
    ) {}

    private bool $createMissingClients = false;

    /**
     * Manual CSV imports create the client of an invoice/quote when it does not exist yet.
     */
    public function createMissingClients(bool $enabled = true): static
    {
        $this->createMissingClients = $enabled;

        return $this;
    }

    public function typeLabel(string $type): string
    {
        return match ($type) {
            'clients' => __('Clients'),
            'invoices' => __('Invoices'),
            'proposals' => __('Quotes'),
            'products' => __('Products'),
            'prospects' => __('Prospects'),
            default => Str::headline($type),
        };
    }

    /**
     * Expected CSV columns for the upload UI and example files.
     *
     * @return list<array{key: string, required: bool, aliases: list<string>, description: string}>
     */
    public function columnsFor(string $type): array
    {
        return match ($type) {
            'clients' => [
                $this->column('name', true, ['nom', 'nom_client', 'client', 'full_name', 'nom_complet'], __('data_import_col_name')),
                $this->column('email', false, ['e_mail', 'mail', 'courriel', 'adresse_email', 'adresse_e_mail', 'email_principal', 'e_mail_principal', 'mail_principal'], __('data_import_col_email')),
                $this->column('phone', false, ['telephone', 'tel', 'mobile', 'portable', 'gsm', 'numero', 'numero_telephone', 'telephone_principal'], __('data_import_col_phone')),
                $this->column('company_name', false, ['societe', 'entreprise', 'company', 'raison_sociale', 'organisation', 'organization'], __('data_import_col_client_company_name')),
                $this->column('contact_name', false, ['contact', 'contact_principal', 'nom_contact', 'main_contact', 'interlocuteur'], __('data_import_col_client_contact_name')),
                $this->column('category', false, ['categorie', 'type', 'segment'], __('data_import_col_client_category')),
                $this->column('status', false, ['statut', 'etat', 'actif', 'active', 'is_active'], __('data_import_col_client_status')),
                $this->column('address_line1', false, ['adresse', 'address', 'rue'], __('data_import_col_client_address')),
                $this->column('address_postal_code', false, ['code_postal', 'codepostal', 'cp', 'zip', 'zip_code', 'zipcode', 'postal_code', 'postcode', 'code_postale'], __('data_import_col_client_postal_code')),
                $this->column('address_city', false, ['ville', 'city'], __('data_import_col_client_city')),
                $this->column('address_country', false, ['pays', 'country'], __('data_import_col_client_country')),
                $this->column('created_at', false, ['date_de_creation', 'date_creation', 'cree_le', 'date_ajout', 'created'], __('data_import_col_client_created_at')),
            ],
            'invoices' => [
                $this->column('ref', false, ['invoice_ref', 'document_ref', 'invoice_number', 'n_facture', 'no_facture', 'num_facture', 'numero_facture', 'numero', 'facture', 'n'], __('data_import_col_ref')),
                $this->column('client_email', false, ['email', 'e_mail', 'email_client', 'e_mail_client', 'mail_client', 'courriel'], __('data_import_col_client_email')),
                $this->column('client_name', false, ['client', 'nom_client', 'customer', 'societe', 'raison_sociale', 'entreprise', 'code_client'], __('data_import_col_client_name')),
                $this->column('issue_date', false, ['date_facture', 'date_de_facture', 'date_d_emission', 'date_emission', 'invoice_date', 'date'], __('data_import_col_issue_date')),
                $this->column('due_date', false, ['date_echeance', 'date_d_echeance', 'echeance', 'date_limite', 'due'], __('data_import_col_due_date')),
                $this->column('status', false, ['statut', 'etat'], __('data_import_col_invoice_status')),
                $this->column('currency', false, ['devise', 'monnaie'], __('data_import_col_currency')),
                $this->column('line_title', false, ['titre_ligne', 'intitule_ligne', 'item_title', 'titre_article'], __('data_import_col_line_title')),
                $this->column('description', false, ['line_description', 'designation', 'libelle', 'prestation', 'article', 'produit', 'objet'], __('data_import_col_line_description')),
                $this->column('quantity', false, ['qty', 'quantite', 'qte'], __('data_import_col_quantity')),
                $this->column('unit_price', false, ['unit_amount', 'price', 'prix_unitaire', 'prix_unitaire_ht', 'pu_ht', 'pu', 'prix', 'prix_ht'], __('data_import_col_unit_price')),
                $this->column('vat_rate', false, ['taux_tva', 'taux_de_tva', 'tva_percent', 'tva_pourcent', 'tva_ligne', 'vat_rate', 'tax_rate', 'taux_taxe'], __('data_import_col_line_vat_rate')),
                $this->column('amount_ht', false, ['montant_ht', 'total_ht', 'sous_total', 'subtotal', 'montant'], __('data_import_col_amount_ht')),
                $this->column('customer_notes', false, ['notes', 'note', 'commentaire', 'remarque'], __('data_import_col_notes')),
                $this->column('line_items', false, ['items'], __('data_import_col_line_items_json')),
            ],
            'proposals' => [
                $this->column('ref', false, ['proposal_ref', 'document_ref', 'n_devis', 'no_devis', 'num_devis', 'numero_devis', 'numero', 'devis', 'n'], __('data_import_col_ref')),
                $this->column('client_email', false, ['email', 'e_mail', 'email_client', 'e_mail_client', 'mail_client', 'courriel'], __('data_import_col_client_email')),
                $this->column('client_name', false, ['client', 'nom_client', 'customer', 'societe', 'raison_sociale', 'entreprise', 'code_client'], __('data_import_col_client_name')),
                $this->column('name', false, ['title', 'titre', 'intitule', 'nom_devis', 'sujet', 'objet'], __('data_import_col_quote_name')),
                $this->column('issue_date', false, ['date_devis', 'date_de_devis', 'date_emission', 'date'], __('data_import_col_issue_date')),
                $this->column('valid_until', false, ['date_validite', 'date_de_validite', 'validite', 'valable_jusqu_au', 'expiration', 'date_expiration'], __('data_import_col_valid_until')),
                $this->column('currency', false, ['devise', 'monnaie'], __('data_import_col_currency')),
                $this->column('line_title', false, ['titre_ligne', 'intitule_ligne', 'item_title', 'titre_article'], __('data_import_col_line_title')),
                $this->column('description', false, ['line_description', 'designation', 'libelle', 'prestation', 'article', 'produit'], __('data_import_col_line_description')),
                $this->column('quantity', false, ['qty', 'quantite', 'qte'], __('data_import_col_quantity')),
                $this->column('unit_price', false, ['unit_amount', 'price', 'prix_unitaire', 'prix_unitaire_ht', 'pu_ht', 'pu', 'prix', 'prix_ht'], __('data_import_col_unit_price')),
                $this->column('vat_rate', false, ['taux_tva', 'taux_de_tva', 'tva_percent', 'tva_pourcent', 'tva_ligne', 'vat_rate', 'tax_rate', 'taux_taxe'], __('data_import_col_line_vat_rate')),
                $this->column('amount_ht', false, ['montant_ht', 'total_ht', 'sous_total', 'subtotal', 'montant'], __('data_import_col_amount_ht')),
                $this->column('customer_notes', false, ['notes', 'note', 'commentaire', 'remarque'], __('data_import_col_notes')),
                $this->column('line_items', false, ['items'], __('data_import_col_line_items_json')),
            ],
            'products' => [
                $this->column('name', true, ['designation', 'nom', 'libelle', 'produit', 'article', 'nom_produit', 'title', 'titre'], __('data_import_col_product_name')),
                $this->column('sku', false, ['reference', 'ref', 'code', 'code_article', 'code_produit', 'ean'], __('data_import_col_sku')),
                $this->column('price', false, ['base_price', 'prix', 'prix_ht', 'prix_unitaire', 'prix_unitaire_ht', 'pu_ht', 'tarif', 'prix_de_vente'], __('data_import_col_price')),
                $this->column('currency', false, ['devise', 'monnaie'], __('data_import_col_currency')),
                $this->column('unit', false, ['unite'], __('data_import_col_unit')),
                $this->column('stock_qty', false, ['stock', 'quantite', 'qte', 'quantite_en_stock', 'qte_stock'], __('data_import_col_stock')),
                $this->column('track_stock', false, ['suivi_stock', 'gestion_stock'], __('data_import_col_track_stock')),
                $this->column('is_service', false, ['service', 'est_service'], __('data_import_col_is_service')),
                $this->column('type', false, ['type_produit', 'nature', 'kind'], __('data_import_col_product_type')),
                $this->column('description', false, ['descriptif', 'details'], __('data_import_col_description')),
                $this->column('tax_rate', false, ['taxe_1', 'taxe', 'tax', 'tax_1', 'tva', 'tva_1', 'taux_tva', 'taux_de_tva', 'tva_percent', 'vat', 'vat_rate', 'tax_rate_1'], __('data_import_col_tax_rate')),
                $this->column('tax_rate_2', false, ['taxe_2', 'tax_2', 'tva_2', 'taxe_secondaire'], __('data_import_col_tax_rate_2')),
                $this->column('category', false, ['nom_du_groupe', 'groupe', 'group', 'categorie', 'famille', 'rubrique'], __('data_import_col_category')),
            ],
            'prospects' => [
                $this->column('name', false, ['nom', 'nom_complet', 'full_name', 'contact', 'contact_principal', 'nom_contact', 'interlocuteur'], __('data_import_col_prospect_name')),
                $this->column('email', false, ['e_mail', 'mail', 'courriel', 'adresse_email', 'adresse_e_mail', 'email_principal', 'e_mail_principal'], __('data_import_col_prospect_email')),
                $this->column('phone', false, ['telephone', 'tel', 'mobile', 'portable', 'gsm'], __('data_import_col_phone')),
                $this->column('whatsapp', false, ['wa'], __('data_import_col_whatsapp')),
                $this->column('company', false, ['company_name', 'societe', 'entreprise', 'raison_sociale', 'organisation', 'organization'], __('data_import_col_prospect_company')),
                $this->column('job_title', false, ['title', 'poste', 'fonction', 'titre'], __('data_import_col_job_title')),
                $this->column('source', false, ['origine', 'provenance', 'canal'], __('data_import_col_source')),
                $this->column('status', false, ['statut', 'etat'], __('data_import_col_prospect_status')),
                $this->column('notes', false, ['note', 'commentaire', 'remarque'], __('data_import_col_prospect_notes')),
                $this->column('linkedin', false, [], __('data_import_col_linkedin')),
                $this->column('website', false, ['site', 'site_web', 'site_internet', 'web'], __('data_import_col_website')),
            ],
            default => [],
        };
    }

    /**
     * Sample CSV matching the expected columns for each import type.
     */
    public function exampleCsv(string $type): string
    {
        return match ($type) {
            'clients' => $this->csvLines([
                ['name', 'email', 'phone', 'company_name', 'contact_name', 'category', 'status'],
                ['Acme Corp', 'billing@acme.example', '+1 555 0100', 'Acme Corp SARL', 'Jean Martin', 'PME', 'actif'],
                ['Jane Doe', 'jane@example.com', '+1 555 0101', '', 'Jane Doe', 'Particulier', 'inactif'],
                ['Walk-in client', '', '', '', '', '', ''],
            ]),
            'invoices' => $this->csvLines([
                ['ref', 'client_email', 'client_name', 'currency', 'status', 'due_date', 'customer_notes', 'description', 'quantity', 'unit_price'],
                ['INV-DEMO-1', 'billing@acme.example', '', 'USD', 'draft', '2026-12-31', 'Thanks for your business', 'Website redesign', '1', '2500.00'],
                ['INV-DEMO-1', 'billing@acme.example', '', 'USD', 'draft', '2026-12-31', '', 'Hosting (12 months)', '1', '240.00'],
                ['INV-DEMO-2', '', 'Jane Doe', 'EUR', 'draft', '', '', 'Consulting day', '2', '750.00'],
            ]),
            'proposals' => $this->csvLines([
                ['ref', 'client_email', 'client_name', 'name', 'currency', 'valid_until', 'customer_notes', 'description', 'quantity', 'unit_price'],
                ['Q-DEMO-1', 'billing@acme.example', '', 'Website proposal', 'USD', '2026-10-31', 'Valid 30 days', 'Discovery workshop', '1', '900.00'],
                ['Q-DEMO-1', 'billing@acme.example', '', 'Website proposal', 'USD', '2026-10-31', '', 'UI design package', '1', '3200.00'],
                ['Q-DEMO-2', '', 'Jane Doe', 'Support retainer', 'EUR', '', '', 'Monthly support', '1', '450.00'],
            ]),
            'products' => $this->csvLines([
                ['name', 'sku', 'price', 'currency', 'unit', 'stock_qty', 'track_stock', 'is_service', 'description'],
                ['Website audit', 'SVC-AUDIT', '450.00', 'USD', 'hour', '0', 'false', 'true', 'Half-day technical audit'],
                ['Branded notebook', 'SKU-NB-01', '12.50', 'USD', 'each', '120', 'true', 'false', 'A5 hardcover notebook'],
                ['Support retainer', 'SVC-RET', '450.00', 'EUR', 'month', '0', 'false', 'true', 'Monthly support package'],
            ]),
            'prospects' => $this->csvLines([
                ['name', 'email', 'phone', 'whatsapp', 'company', 'job_title', 'source', 'notes', 'linkedin', 'website'],
                ['Alex Rivera', 'alex@example.com', '+1 555 0200', '+1 555 0200', 'Rivera Studio', 'Owner', 'linkedin', 'Met at a trade show', 'https://linkedin.com/in/alexrivera', 'https://rivera.example'],
                ['Sam Chen', 'sam@example.com', '+1 555 0201', '', 'Chen Media', 'Marketing lead', 'import', 'Asked for a quote', '', ''],
                ['Walk-in lead', '', '+1 555 0202', '', '', '', 'other', '', '', ''],
            ]),
            default => '',
        };
    }

    /**
     * @param  list<string>  $headerKeys
     * @return array{matched: list<string>, unknown: list<string>, missing_required: list<string>}
     */
    public function classifyHeaders(string $type, array $headerKeys): array
    {
        $columns = $this->columnsFor($type);
        $normalizedHeaders = [];
        foreach ($headerKeys as $key) {
            $normalizedHeaders[self::matchKey((string) $key)] ??= (string) $key;
        }

        $matched = [];
        $covered = [];
        $mapping = [];
        foreach ($columns as $column) {
            $candidates = array_merge([$column['key']], $column['aliases']);
            foreach ($candidates as $candidate) {
                $needle = self::matchKey($candidate);
                if (isset($normalizedHeaders[$needle]) && ! isset($mapping[$normalizedHeaders[$needle]])) {
                    $matched[] = $normalizedHeaders[$needle];
                    $mapping[$normalizedHeaders[$needle]] = $column['key'];
                    $covered[$column['key']] = true;
                    break;
                }
            }
        }

        $unknown = [];
        foreach (array_map('strval', $headerKeys) as $original) {
            if (! in_array($original, $matched, true)) {
                $unknown[] = $original;
            }
        }

        $missingRequired = [];
        foreach ($columns as $column) {
            if ($column['required'] && ! isset($covered[$column['key']])) {
                $missingRequired[] = $column['key'];
            }
        }

        // Document imports need client identity, not a single required header.
        if (in_array($type, ['invoices', 'proposals'], true)) {
            $hasClient = isset($covered['client_email']) || isset($covered['client_name']);
            if (! $hasClient) {
                $missingRequired[] = 'client_email|client_name';
            }
        }

        if ($type === 'products' && ! isset($covered['name']) && isset($covered['sku'])) {
            $missingRequired = array_values(array_diff($missingRequired, ['name']));
        }

        $notes = [];
        if ($type === 'clients' && ! isset($covered['name']) && (isset($covered['company_name']) || isset($covered['contact_name']))) {
            $missingRequired = array_values(array_diff($missingRequired, ['name']));
            $notes[] = 'name_fallback';
        }

        if ($type === 'prospects') {
            $hasIdentity = isset($covered['name']) || isset($covered['email']) || isset($covered['company']);
            if (! $hasIdentity) {
                $missingRequired[] = 'name|email';
            }
        }

        return [
            'matched' => array_values(array_unique($matched)),
            'unknown' => array_values(array_unique($unknown)),
            'missing_required' => array_values(array_unique($missingRequired)),
            'mapping' => $mapping,
            'notes' => $notes,
        ];
    }

    /**
     * Header key used for alias matching: normalised and plural-insensitive (catégories = categorie).
     */
    public static function matchKey(string $header): string
    {
        $parts = explode('_', self::headerKey($header));
        $parts = array_map(fn (string $p): string => strlen($p) > 3 && str_ends_with($p, 's') ? substr($p, 0, -1) : $p, $parts);

        return implode('_', $parts);
    }

    /**
     * Target fields offered in the manual mapping select, per import type (key => French label).
     *
     * @return array<string, string>
     */
    public function fieldLabelsFor(string $type): array
    {
        if ($type === 'clients') {
            return $this->clientFieldLabels();
        }
        $labels = [];
        foreach ($this->columnsFor($type) as $column) {
            $labels[$column['key']] = __('data_import_field_'.$type.'_'.$column['key']);
        }

        return $labels;
    }

    /**
     * Rules for the mapping UI: groups of fields where at least one must be mapped, and info notes.
     *
     * @return array{groups: list<array{keys: list<string>, message: string}>, notes: list<array{text: string, missing: ?string, any: list<string>}>}
     */
    public function mappingRulesFor(string $type): array
    {
        return match ($type) {
            'clients' => [
                'groups' => [['keys' => ['name', 'company_name', 'contact_name', 'email'], 'message' => __('data_import_mapping_need_identity')]],
                'notes' => [['text' => __('data_import_mapping_name_fallback'), 'missing' => 'name', 'any' => ['company_name', 'contact_name']]],
            ],
            'invoices', 'proposals' => [
                'groups' => [
                    ['keys' => ['client_email', 'client_name'], 'message' => __('data_import_mapping_need_client')],
                    ['keys' => ['description', 'unit_price', 'amount_ht', 'line_items'], 'message' => __('data_import_mapping_need_lines')],
                ],
                'notes' => [
                    ['text' => __('data_import_note_doc_numbering_'.$type), 'missing' => null, 'any' => []],
                    ['text' => __('data_import_note_doc_taxes'), 'missing' => null, 'any' => []],
                    ['text' => __('data_import_note_doc_clients'), 'missing' => null, 'any' => []],
                    ['text' => __('data_import_note_doc_single_line'), 'missing' => 'description', 'any' => ['amount_ht', 'unit_price']],
                ],
            ],
            'products' => [
                'groups' => [['keys' => ['name', 'sku'], 'message' => __('data_import_mapping_need_product')]],
                'notes' => [
                    ['text' => __('data_import_note_product_name_from_sku'), 'missing' => 'name', 'any' => ['sku']],
                    ['text' => __('data_import_note_product_update'), 'missing' => null, 'any' => []],
                ],
            ],
            'prospects' => [
                'groups' => [['keys' => ['name', 'email', 'company'], 'message' => __('data_import_mapping_need_prospect')]],
                'notes' => [['text' => __('data_import_note_prospect_name_from_company'), 'missing' => 'name', 'any' => ['company']]],
            ],
            default => ['groups' => [], 'notes' => []],
        };
    }

    /**
     * Validate a manual mapping (original header => field key or '') for any import type.
     *
     * @param  list<string>  $headerKeys
     * @param  array<string, string>  $mapping
     * @return list<string>
     */
    public function validateMapping(string $type, array $headerKeys, array $mapping): array
    {
        $labels = $this->fieldLabelsFor($type);
        $errors = [];
        $used = [];
        foreach ($headerKeys as $header) {
            $target = (string) ($mapping[$header] ?? '');
            if ($target === '') {
                continue;
            }
            if (! isset($labels[$target])) {
                $errors[] = __('data_import_mapping_unknown_field', ['column' => $header]);

                continue;
            }
            if (isset($used[$target])) {
                $errors[] = __('data_import_mapping_duplicate', ['field' => $labels[$target]]);
            }
            $used[$target] = true;
        }
        foreach ($this->mappingRulesFor($type)['groups'] as $group) {
            if (array_intersect($group['keys'], array_keys($used)) === []) {
                $errors[] = $group['message'];
            }
        }

        return array_values(array_unique($errors));
    }

    /**
     * Re-key rows with a manual mapping for any type; ignored columns are dropped.
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, string>  $mapping
     * @return list<array<string, mixed>>
     */
    public function applyMapping(string $type, array $rows, array $mapping): array
    {
        return $this->applyClientMapping($rows, $mapping);
    }

    /**
     * Parse a French- or English-formatted number ("1 234,56", "1.234,56", "1,234.56", "12 €") to a dot-decimal string.
     */
    public function parseDecimal(mixed $value): ?string
    {
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        $s = trim((string) ($value ?? ''));
        if ($s === '') {
            return null;
        }
        $negative = str_starts_with($s, '-') || (str_starts_with($s, '(') && str_ends_with($s, ')'));
        $s = (string) preg_replace('/[^0-9,\.]/u', '', $s);
        if ($s === '' || ! preg_match('/\d/', $s)) {
            return null;
        }
        $lastComma = strrpos($s, ',');
        $lastDot = strrpos($s, '.');
        if ($lastComma !== false && $lastDot !== false) {
            $decimalSep = $lastComma > $lastDot ? ',' : '.';
            $thousandSep = $decimalSep === ',' ? '.' : ',';
            $s = str_replace($thousandSep, '', $s);
            $s = str_replace($decimalSep, '.', $s);
        } elseif ($lastComma !== false) {
            $s = substr_count($s, ',') > 1 ? str_replace(',', '', $s) : str_replace(',', '.', $s);
        } elseif ($lastDot !== false && substr_count($s, '.') > 1) {
            $s = str_replace('.', '', $s);
        }
        if (! is_numeric($s)) {
            return null;
        }

        return ($negative ? '-' : '').$s;
    }

    /**
     * Decimal amount (string/number, French formats accepted) to minor units.
     */
    private function amountToMinor(mixed $value, string $currency): ?int
    {
        $decimal = $this->parseDecimal($value);

        return $decimal === null ? null : flowdesk_decimal_to_minor($decimal, $currency);
    }

    /**
     * Normalise a date cell to Y-m-d (French d/m/Y accepted); null when empty or unparseable.
     */
    public function normalizeDate(mixed $value): ?string
    {
        return $this->parseImportDate($value, true)?->toDateString();
    }

    public function invoiceStatusFromText(mixed $value): ?InvoiceStatus
    {
        $s = self::headerKey((string) ($value ?? ''));
        if ($s === '') {
            return null;
        }

        return match ($s) {
            'brouillon', 'draft' => InvoiceStatus::Draft,
            'envoyee', 'envoye', 'emise', 'emis', 'sent', 'en_attente', 'a_payer', 'non_payee', 'impayee' => InvoiceStatus::Sent,
            'payee', 'paye', 'reglee', 'regle', 'paid', 'acquittee', 'soldee' => InvoiceStatus::Paid,
            'en_retard', 'retard', 'overdue', 'echue' => InvoiceStatus::Overdue,
            'annulee', 'annule', 'cancelled', 'canceled' => InvoiceStatus::Cancelled,
            default => InvoiceStatus::tryFrom($s),
        };
    }

    /**
     * French labels for the manual column mapping select (client import).
     *
     * @return array<string, string>
     */
    public function clientFieldLabels(): array
    {
        return [
            'name' => __('data_import_field_name'),
            'email' => __('data_import_field_email'),
            'phone' => __('data_import_field_phone'),
            'company_name' => __('client_company_name_label'),
            'contact_name' => __('client_contact_name_label'),
            'category' => __('client_category_label'),
            'status' => __('data_import_field_status'),
            'address_line1' => __('data_import_field_address'),
            'address_postal_code' => __('data_import_field_postal_code'),
            'address_city' => __('data_import_field_city'),
            'address_country' => __('data_import_field_country'),
            'created_at' => __('data_import_field_created_at'),
        ];
    }

    /**
     * Validate a manual mapping (original header => target key or '') for the client import.
     *
     * @param  list<string>  $headerKeys
     * @param  array<string, string>  $mapping
     * @return list<string> error messages
     */
    public function validateClientMapping(array $headerKeys, array $mapping): array
    {
        $allowed = array_column($this->columnsFor('clients'), 'key');
        $errors = [];
        $used = [];
        foreach ($headerKeys as $header) {
            $target = (string) ($mapping[$header] ?? '');
            if ($target === '') {
                continue;
            }
            if (! in_array($target, $allowed, true)) {
                $errors[] = __('data_import_mapping_unknown_field', ['column' => $header]);

                continue;
            }
            if (isset($used[$target])) {
                $errors[] = __('data_import_mapping_duplicate', ['field' => $this->clientFieldLabels()[$target] ?? $target]);
            }
            $used[$target] = true;
        }
        if (! isset($used['name']) && ! isset($used['company_name']) && ! isset($used['contact_name']) && ! isset($used['email'])) {
            $errors[] = __('data_import_mapping_need_identity');
        }

        return array_values(array_unique($errors));
    }

    /**
     * Re-key rows with a manual mapping; ignored columns are dropped.
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, string>  $mapping
     * @return list<array<string, mixed>>
     */
    public function applyClientMapping(array $rows, array $mapping): array
    {
        return array_map(function (array $row) use ($mapping): array {
            $out = [];
            foreach ($row as $key => $value) {
                $target = (string) ($mapping[(string) $key] ?? '');
                if ($target !== '' && ! array_key_exists($target, $out)) {
                    $out[$target] = $value;
                }
            }

            return $out;
        }, $rows);
    }

    /**
     * Parse a creation date (d/m/Y, d/m/Y H:i, Y-m-d, Y-m-d H:i:s…); null when invalid or in the future.
     */
    public function parseImportDate(mixed $value, bool $allowFuture = false): ?\Illuminate\Support\Carbon
    {
        $s = trim((string) ($value ?? ''));
        if ($s === '') {
            return null;
        }
        foreach (['d/m/Y H:i:s', 'd/m/Y H:i', 'd/m/Y', 'j/n/Y H:i', 'j/n/Y', 'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d', 'd-m-Y H:i', 'd-m-Y', 'd.m.Y', 'Y/m/d'] as $format) {
            try {
                $date = \Illuminate\Support\Carbon::createFromFormat('!'.$format, $s);
            } catch (\Throwable) {
                continue;
            }
            if ($date && $date->format($format) === $s && $date->year >= 1990 && ($allowFuture ? $date->year <= 2100 : $date->lessThanOrEqualTo(now()->addDay()))) {
                return $date;
            }
        }

        return null;
    }

    /**
     * Normalise a CSV header for matching: lower-case, accents stripped, spaces/dashes to underscores.
     */
    public static function headerKey(string $header): string
    {
        $header = preg_replace('/^\x{FEFF}/u', '', trim($header)) ?? trim($header);

        $key = trim((string) preg_replace('/[^a-z0-9]+/', '_', Str::lower(Str::ascii($header))), '_');

        return (string) preg_replace('/_(eur|euro|euros|tnd|dt|usd)$/', '', $key);
    }

    /**
     * Re-key a parsed client row to canonical column keys using header aliases.
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, string>  $mapping
     * @return array<string, mixed>
     */
    private function canonicalRow(array $row, array $mapping): array
    {
        $out = [];
        foreach ($row as $key => $value) {
            $target = $mapping[(string) $key] ?? null;
            if ($target !== null && ! array_key_exists($target, $out)) {
                $out[$target] = $value;
            }
        }

        return $out;
    }

    /**
     * Map a free-text status (FR/EN) to a client status; null when empty or unknown.
     */
    public function clientStatusFromText(mixed $value): ?ClientStatus
    {
        $s = self::headerKey((string) ($value ?? ''));
        if ($s === '') {
            return null;
        }

        return match ($s) {
            'actif', 'active', 'activ', 'client_actif', 'oui', 'yes', 'y', 'o', '1', 'true', 'vrai', 'x', 'on' => ClientStatus::Active,
            'inactif', 'inactive', 'inactiv', 'client_inactif', 'desactive', 'archive', 'non', 'no', 'n', '0', 'false', 'faux', 'off' => ClientStatus::Inactive,
            'potentiel', 'potential', 'prospect', 'client_potentiel', 'lead' => ClientStatus::Potential,
            default => ClientStatus::tryFrom($s),
        };
    }

    /**
     * @param  list<string>  $aliases
     * @return array{key: string, required: bool, aliases: list<string>, description: string}
     */
    private function column(string $key, bool $required, array $aliases, string $description): array
    {
        return [
            'key' => $key,
            'required' => $required,
            'aliases' => $aliases,
            'description' => $description,
        ];
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function csvLines(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $csv = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $csv;
    }

    /**
     * @return list<array<string, string|null>>
     */
    public function parseDelimited(string $raw): array
    {
        return $this->csv->parseDelimited($raw);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{created: int, updated: int, errors: list<string>}
     */
    public function importClients(Company $company, array $rows): array
    {
        $created = 0;
        $updated = 0;
        $errors = [];

        $mappings = [];

        foreach ($rows as $index => $rawRow) {
            $line = $index + 1;
            $signature = implode("\x1f", array_keys($rawRow));
            $mappings[$signature] ??= $this->classifyHeaders('clients', array_keys($rawRow))['mapping'];
            $row = $this->canonicalRow($rawRow, $mappings[$signature]);
            $name = trim((string) ($row['name'] ?? ''));
            $email = $this->normalizeEmail($row['email'] ?? null);

            $companyName = $this->nullableString($row['company_name'] ?? null, 191);
            $contactName = $this->nullableString($row['contact_name'] ?? null, 191);
            if ($name === '') {
                $name = (string) ($companyName ?? $contactName ?? '');
            }

            if ($name === '' && $email === null) {
                $errors[] = __('Row :line: name or email required.', ['line' => $line]);

                continue;
            }
            if ($name === '') {
                $name = Str::before((string) $email, '@') ?: __('Client');
            }

            $status = $this->clientStatusFromText($row['status'] ?? null);
            $address = array_filter([
                'line1' => $this->nullableString($row['address_line1'] ?? null, 255),
                'postal_code' => $this->nullableString($row['address_postal_code'] ?? null, 20),
                'city' => $this->nullableString($row['address_city'] ?? null, 120),
                'country' => $this->nullableString($row['address_country'] ?? null, 60),
            ], fn ($v) => $v !== null);
            $createdAt = $this->parseImportDate($row['created_at'] ?? null);

            // Only non-empty values overwrite an existing client on update.
            $payload = [
                'name' => mb_substr($name, 0, 255),
                'phone' => $this->nullableString($row['phone'] ?? null, 60),
                'company_name' => $companyName,
                'contact_name' => $contactName,
                'category' => $this->nullableString($row['category'] ?? null, 100),
                'status' => $status?->value,
                'address' => $address === [] ? null : $address,
            ];
            $createDefaults = [
                'source' => ClientSource::Other->value,
                'status' => ($status ?? ClientStatus::Active)->value,
            ];
            if ($createdAt !== null) {
                $createDefaults['created_at'] = $createdAt;
            }

            if ($email !== null) {
                $client = Client::query()->withoutGlobalScopes()->firstOrCreate(
                    ['company_id' => $company->id, 'email' => $email],
                    array_merge($payload, $createDefaults),
                );
                if ($client->wasRecentlyCreated) {
                    $this->clientCodes->assignIfMissing($client);
                    $created++;
                } else {
                    $client->fill(array_filter($payload, fn ($v) => $v !== null))->save();
                    $updated++;
                }
            } else {
                $client = Client::query()->withoutGlobalScopes()->create([
                    'company_id' => $company->id,
                    'email' => null,
                    ...$payload,
                    ...$createDefaults,
                ]);
                $this->clientCodes->assignIfMissing($client);
                $created++;
            }
        }

        return compact('created', 'updated', 'errors');
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{created: int, errors: list<string>}
     */
    public function importInvoices(Company $company, array $rows): array
    {
        $created = 0;
        $errors = [];

        $groups = $this->groupDocumentRows($rows, 'invoice');

        foreach ($groups as $groupKey => $groupRows) {
            $line = (int) ($groupRows[0]['_line'] ?? 1);
            try {
                $first = $groupRows[0];
                $client = $this->resolveClientFromRow($company, $first);
                if (! $client) {
                    $errors[] = __('Row :line: client not found (set client_email or client_name).', ['line' => $line]);

                    continue;
                }

                $currency = flowdesk_normalize_currency_code($this->currencyFromText($first['currency'] ?? null) ?? $company->default_currency ?? flowdesk_default_currency());
                $items = $this->lineItemsFromGroup($groupRows, $currency);
                if ($items === []) {
                    $errors[] = __('Row :line: no line items.', ['line' => $line]);

                    continue;
                }

                $taxes = $this->importTaxes($items, $company);

                DB::transaction(function () use ($company, $client, $first, $currency, $items, $taxes, &$created): void {
                    $status = $this->invoiceStatusFromText($first['status'] ?? null) ?? InvoiceStatus::Draft;
                    $issuedAt = $this->parseImportDate($first['issue_date'] ?? null);

                    $invoice = Invoice::query()->withoutGlobalScopes()->create([
                        ...($issuedAt ? ['created_at' => $issuedAt] : []),
                        'company_id' => $company->id,
                        'client_id' => $client->id,
                        'proposal_id' => null,
                        'project_id' => null,
                        'number' => null,
                        'status' => $status,
                        'subtotal_amount' => $taxes['subtotal'],
                        'vat_amount' => $taxes['vat'],
                        'fiscal_stamp_amount' => $taxes['stamp'],
                        'amount' => $taxes['total'],
                        'currency' => $currency,
                        'due_date' => $this->normalizeDate($first['due_date'] ?? null),
                        'customer_notes' => $this->nullableString($first['customer_notes'] ?? $first['notes'] ?? null, 10000),
                    ]);

                    foreach ($items as $item) {
                        $lineTotal = $item['quantity'] * $item['unit_amount'];
                        InvoiceItem::query()->withoutGlobalScopes()->create([
                            'company_id' => $company->id,
                            'invoice_id' => $invoice->id,
                            'title' => $item['title'] ?? null,
                            'description' => $item['description'],
                            'quantity' => $item['quantity'],
                            'unit_amount' => $item['unit_amount'],
                            'total_amount' => $lineTotal,
                            'vat_rate' => $item['vat_rate'] ?? null,
                        ]);
                    }

                    $this->invoiceRefs->assignNextNumber($invoice, $company);
                    $created++;
                });
            } catch (\Throwable $e) {
                $errors[] = __('Row :line: :message', ['line' => $line, 'message' => $e->getMessage()]);
            }
        }

        return ['created' => $created, 'updated' => 0, 'errors' => $errors];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{created: int, errors: list<string>}
     */
    public function importProposals(Company $company, array $rows): array
    {
        $created = 0;
        $errors = [];

        $groups = $this->groupDocumentRows($rows, 'proposal');

        foreach ($groups as $groupRows) {
            $line = (int) ($groupRows[0]['_line'] ?? 1);
            try {
                $first = $groupRows[0];
                $client = $this->resolveClientFromRow($company, $first);
                if (! $client) {
                    $errors[] = __('Row :line: client not found (set client_email or client_name).', ['line' => $line]);

                    continue;
                }

                $currency = flowdesk_normalize_currency_code($this->currencyFromText($first['currency'] ?? null) ?? $company->default_currency ?? flowdesk_default_currency());
                $items = $this->lineItemsFromGroup($groupRows, $currency);
                if ($items === []) {
                    $errors[] = __('Row :line: no line items.', ['line' => $line]);

                    continue;
                }

                $taxes = $this->importTaxes($items, $company);
                $name = $this->nullableString($first['name'] ?? $first['title'] ?? null, 255)
                    ?? __('Quote');

                DB::transaction(function () use ($company, $client, $first, $currency, $items, $taxes, $name, &$created): void {
                    $issuedAt = $this->parseImportDate($first['issue_date'] ?? null);
                    $proposal = Proposal::query()->withoutGlobalScopes()->create([
                        ...($issuedAt ? ['created_at' => $issuedAt] : []),
                        'company_id' => $company->id,
                        'client_id' => $client->id,
                        'project_id' => null,
                        'name' => $name,
                        'number' => null,
                        'status' => ProposalStatus::Draft,
                        'subtotal_amount' => $taxes['subtotal'],
                        'vat_amount' => $taxes['vat'],
                        'fiscal_stamp_amount' => $taxes['stamp'],
                        'amount' => $taxes['total'],
                        'currency' => $currency,
                        'valid_until' => $this->normalizeDate($first['valid_until'] ?? null),
                        'customer_notes' => $this->nullableString($first['customer_notes'] ?? $first['notes'] ?? null, 10000),
                    ]);

                    foreach ($items as $item) {
                        $lineTotal = $item['quantity'] * $item['unit_amount'];
                        ProposalItem::query()->withoutGlobalScopes()->create([
                            'company_id' => $company->id,
                            'proposal_id' => $proposal->id,
                            'title' => $item['title'] ?? null,
                            'description' => $item['description'],
                            'quantity' => $item['quantity'],
                            'unit_amount' => $item['unit_amount'],
                            'total_amount' => $lineTotal,
                            'vat_rate' => $item['vat_rate'] ?? null,
                        ]);
                    }

                    $this->proposalRefs->assignNextNumber($proposal, $company);
                    $created++;
                });
            } catch (\Throwable $e) {
                $errors[] = __('Row :line: :message', ['line' => $line, 'message' => $e->getMessage()]);
            }
        }

        return ['created' => $created, 'updated' => 0, 'errors' => $errors];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{created: int, updated: int, errors: list<string>}
     */
    public function importProducts(Company $company, array $rows): array
    {
        $created = 0;
        $updated = 0;
        $errors = [];
        $createdIds = [];

        foreach ($rows as $index => $row) {
            $line = $index + 1;
            $sku = $this->nullableString(isset($row['sku']) ? (string) $row['sku'] : null, 64);
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '' && $sku !== null) {
                $name = $sku;
            }
            if ($name === '') {
                $errors[] = __('Row :line: product name required.', ['line' => $line]);

                continue;
            }

            $priceRaw = $row['price'] ?? $row['base_price'] ?? null;
            $currency = flowdesk_normalize_currency_code(
                $this->currencyFromText($row['currency'] ?? null)
                ?? $this->currencyFromAmountText($priceRaw)
                ?? $company->default_currency
                ?? flowdesk_default_currency()
            );
            $hasPrice = $priceRaw !== null && trim((string) $priceRaw) !== '';
            $priceMinor = $hasPrice ? (int) ($this->amountToMinor($priceRaw, $currency) ?? 0) : 0;
            $stockRaw = $row['stock_qty'] ?? $row['stock'] ?? null;
            $hasStock = $stockRaw !== null && trim((string) $stockRaw) !== '';
            $classified = $this->classifyImportedProduct($row, $name, $sku, $hasStock);
            $trackStock = $classified['track_stock'];
            $taxRate = $this->parseTaxRate($row['tax_rate'] ?? null);
            $taxRate2 = $this->parseTaxRate($row['tax_rate_2'] ?? null);
            $category = $this->nullableString(isset($row['category']) ? (string) $row['category'] : null, 100);
            $stockQty = $hasStock ? (int) round((float) ($this->parseDecimal($stockRaw) ?? 0)) : 0;
            $unit = $classified['unit'];
            $description = $this->nullableString(isset($row['description']) ? (string) $row['description'] : null, 5000);

            $existing = null;
            if ($sku !== null) {
                $existing = Product::query()->withoutGlobalScopes()
                    ->where('company_id', $company->id)
                    ->where('sku', $sku)
                    ->first();
            } else {
                // Re-importing the same file without SKU updates products with the same name instead of duplicating them.
                $existing = Product::query()->withoutGlobalScopes()
                    ->where('company_id', $company->id)
                    ->whereNull('sku')
                    ->where('name', mb_substr($name, 0, 255))
                    ->whereNotIn('id', $createdIds)
                    ->first();
            }

            if ($existing) {
                // Only overwrite what the file provides (empty price/stock/description keep current values).
                $existing->update(array_filter([
                    'name' => $name,
                    'kind' => $classified['kind'],
                    'unit' => $unit,
                    'description' => $description,
                    'base_price_minor' => $hasPrice ? $priceMinor : null,
                    'currency' => $hasPrice ? $currency : null,
                    'track_stock' => $trackStock,
                    'tax_rate' => $taxRate,
                    'tax_rate_2' => $taxRate2,
                    'category' => $category,
                ], fn ($v) => $v !== null));
                $createdIds[] = $existing->id;
                $variant = $existing->variants()->first();
                if ($variant && $hasStock) {
                    $variant->update(['stock_qty' => $stockQty, 'sku' => $sku]);
                }
                $updated++;
            } else {
                $product = Product::query()->withoutGlobalScopes()->create([
                    'company_id' => $company->id,
                    'name' => mb_substr($name, 0, 255),
                    'kind' => $classified['kind'],
                    'unit' => $unit,
                    'sku' => $sku,
                    'description' => $description,
                    'base_price_minor' => $priceMinor,
                    'currency' => $currency,
                    'track_stock' => $trackStock,
                    'tax_rate' => $taxRate,
                    'tax_rate_2' => $taxRate2,
                    'category' => $category,
                    'is_active' => true,
                ]);
                $createdIds[] = $product->id;
                ProductVariant::query()->create([
                    'company_id' => $company->id,
                    'product_id' => $product->id,
                    'sku' => $sku,
                    'stock_qty' => $stockQty,
                ]);
                $created++;
            }
        }

        return compact('created', 'updated', 'errors');
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{created: int, updated: int, errors: list<string>}
     */
    public function importProspects(Company $company, User $user, array $rows, ?string $filename = null): array
    {
        $rows = array_map(function (array $row): array {
            if (trim((string) ($row['name'] ?? '')) === '') {
                $company = trim((string) ($row['company'] ?? $row['company_name'] ?? ''));
                if ($company !== '') {
                    $row['name'] = $company;
                }
            }

            return $row;
        }, $rows);

        $result = $this->csv->importRows($company, $user, $rows, $filename);

        return [
            'created' => $result['created'],
            'updated' => $result['updated'],
            'errors' => $result['errors'],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, list<array<string, mixed>>>
     */
    private function groupDocumentRows(array $rows, string $prefix): array
    {
        $groups = [];
        foreach ($rows as $index => $row) {
            $row['_line'] = $index + 1;
            $ref = trim((string) ($row['ref'] ?? $row[$prefix.'_ref'] ?? $row['document_ref'] ?? ''));
            if ($ref === '') {
                $ref = 'row-'.($index + 1);
            }
            $groups[$ref][] = $row;
        }

        return $groups;
    }

    /**
     * @param  list<array<string, mixed>>  $groupRows
     * @return list<array{description: string, quantity: int, unit_amount: int}>
     */
    /**
     * Document totals for imported lines. Without any line VAT column the workspace rate applies as before
     * (lines keep vat_rate = null); with one, every line gets its rate (empty = workspace rate).
     *
     * @param  list<array<string, mixed>>  $items  (by reference: vat_rate filled when per-line rates are used)
     * @return array<string, mixed>
     */
    private function importTaxes(array &$items, Company $company): array
    {
        foreach ($items as $k => $item) {
            if (($item['vat_rate'] ?? null) !== null && ((float) $item['vat_rate'] < 0 || (float) $item['vat_rate'] > 100)) {
                $items[$k]['vat_rate'] = null;
            }
        }
        $hasRates = collect($items)->contains(fn ($i) => ($i['vat_rate'] ?? null) !== null);
        if (! $hasRates) {
            $subtotal = 0;
            foreach ($items as $item) {
                $subtotal += $item['quantity'] * $item['unit_amount'];
            }

            return $this->totals->fromSubtotalMinor($subtotal, $company);
        }
        $default = $this->totals->companyVatPercent($company);
        foreach ($items as $k => $item) {
            $items[$k]['vat_rate'] = \App\Services\InvoiceTotalsService::normalizeRate($item['vat_rate'] ?? null, $default);
        }

        return $this->totals->fromItemsMinor(\App\Services\InvoiceTotalsService::rowsFromFormItems($items), $company);
    }

    private function lineItemsFromGroup(array $groupRows, string $currency): array
    {
        $first = $groupRows[0];
        $json = $first['line_items'] ?? $first['items'] ?? null;
        if (is_string($json) && trim($json) !== '') {
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                return $this->normalizeLineItems($decoded, $currency);
            }
        }

        $items = [];
        foreach ($groupRows as $row) {
            $desc = trim((string) ($row['description'] ?? $row['line_description'] ?? ''));
            $lineTitle = flowdesk_line_title($row['line_title'] ?? null);
            $amountHt = $row['amount_ht'] ?? null;
            if ($desc === '' && $lineTitle === null) {
                // One-row-per-document files: a total HT (or unit price) without a line description.
                if ($this->parseDecimal($amountHt) === null && $this->parseDecimal($row['unit_price'] ?? null) === null) {
                    continue;
                }
                $ref = trim((string) ($row['ref'] ?? ''));
                $desc = $ref !== '' ? __('data_import_default_line_ref', ['ref' => $ref]) : __('data_import_default_line');
            }
            $qtyDecimal = $this->parseDecimal($row['quantity'] ?? $row['qty'] ?? null);
            $qty = max(1, (int) round((float) ($qtyDecimal ?? 1)));
            if (array_key_exists('unit_price', $row) || array_key_exists('price', $row)) {
                $unitMinor = (int) ($this->amountToMinor($row['unit_price'] ?? $row['price'] ?? null, $currency) ?? 0);
            } else {
                // Legacy key: integer unit_amount values above 1000 are already minor units.
                $unitRaw = $row['unit_amount'] ?? 0;
                $unitMinor = is_numeric($unitRaw) && ! str_contains((string) $unitRaw, '.') && (int) $unitRaw > 1000
                    ? (int) $unitRaw
                    : (int) ($this->amountToMinor($unitRaw, $currency) ?? 0);
            }
            if ($unitMinor === 0 && $amountHt !== null && trim((string) $amountHt) !== '') {
                $unitMinor = (int) round(((int) $this->amountToMinor($amountHt, $currency)) / $qty);
            }
            $items[] = [
                'title' => $lineTitle,
                'description' => mb_substr($desc, 0, 5000),
                'quantity' => $qty,
                'unit_amount' => $unitMinor,
                'vat_rate' => $this->parseTaxRate($row['vat_rate'] ?? null),
            ];
        }

        return $items;
    }

    /**
     * @param  list<mixed>  $decoded
     * @return list<array{description: string, quantity: int, unit_amount: int}>
     */
    private function normalizeLineItems(array $decoded, string $currency): array
    {
        $items = [];
        foreach ($decoded as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $desc = trim((string) ($entry['description'] ?? ''));
            $lineTitle = flowdesk_line_title($entry['title'] ?? $entry['line_title'] ?? null);
            if ($desc === '' && $lineTitle === null) {
                continue;
            }
            $qty = max(1, (int) ($entry['quantity'] ?? 1));
            $unitRaw = $entry['unit_amount'] ?? $entry['unit_price'] ?? 0;
            $unitMinor = is_numeric($unitRaw) && (int) $unitRaw > 1000 && ! str_contains((string) $unitRaw, '.')
                ? (int) $unitRaw
                : flowdesk_decimal_to_minor((string) $unitRaw, $currency);
            $items[] = [
                'title' => $lineTitle,
                'description' => mb_substr($desc, 0, 5000),
                'quantity' => $qty,
                'unit_amount' => $unitMinor,
                'vat_rate' => $this->parseTaxRate($entry['vat_rate'] ?? $entry['tax_rate'] ?? null),
            ];
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolveClientFromRow(Company $company, array $row): ?Client
    {
        $base = fn () => Client::query()->withoutGlobalScopes()->where('company_id', $company->id);
        $email = $this->normalizeEmail($row['client_email'] ?? $row['email'] ?? null);
        if ($email !== null) {
            $client = $base()->where('email', $email)->first();
            if ($client || ! $this->createMissingClients) {
                return $client ?? $this->createImportedClient($company, $row, $email);
            }
        }

        $name = trim((string) ($row['client_name'] ?? $row['client'] ?? ''));
        if ($name !== '') {
            $lower = mb_strtolower($name);
            $client = $base()->where('code', $name)->first()
                ?? $base()->whereRaw('LOWER(name) = ?', [$lower])->first()
                ?? $base()->whereRaw('LOWER(company_name) = ?', [$lower])->first()
                ?? $base()->whereRaw('LOWER(contact_name) = ?', [$lower])->first();
            if ($client) {
                return $client;
            }
        }

        return $this->createImportedClient($company, $row, $email);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function createImportedClient(Company $company, array $row, ?string $email): ?Client
    {
        if (! $this->createMissingClients) {
            return null;
        }
        $name = trim((string) ($row['client_name'] ?? $row['client'] ?? ''));
        if ($name === '' && $email === null) {
            return null;
        }
        $client = Client::query()->withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => mb_substr($name !== '' ? $name : (Str::before((string) $email, '@') ?: __('Client')), 0, 255),
            'email' => $email,
            'source' => ClientSource::Other->value,
            'status' => ClientStatus::Active->value,
        ]);
        $this->clientCodes->assignIfMissing($client);

        return $client;
    }

    /**
     * Product vs service for an imported row: explicit type/is_service/track_stock columns win, then the
     * catalog keyword matcher; a row with no type, no SKU and no stock is a service (e.g. a price list).
     *
     * @param  array<string, mixed>  $row
     * @return array{kind: string, unit: string, is_service: bool, track_stock: bool}
     */
    private function classifyImportedProduct(array $row, string $name, ?string $sku, bool $hasStock): array
    {
        $signals = [
            'name' => $name,
            'description' => $row['description'] ?? null,
            'unit' => $row['unit'] ?? null,
            'kind' => $row['kind'] ?? null,
            'is_service' => $row['is_service'] ?? null,
            'type' => $row['type'] ?? $row['product_type'] ?? null,
            'track_stock' => $row['track_stock'] ?? true,
        ];
        $classified = \App\Support\CatalogKindMatcher::classify($signals);
        $explicit = array_filter([$row['kind'] ?? null, $row['is_service'] ?? null, $row['type'] ?? null, $row['product_type'] ?? null, $row['track_stock'] ?? null], fn ($v) => $v !== null && trim((string) $v) !== '');
        if ($explicit === [] && $sku === null && ! $hasStock && ! $classified['is_service']) {
            $classified = \App\Support\CatalogKindMatcher::classify(['kind' => \App\Enums\ProductKind::Service->value] + $signals);
        }

        return $classified;
    }

    /** Currency hinted inside an amount cell ("€150,00", "150 DT", "$20", "1 200 TND"). */
    private function currencyFromAmountText(mixed $value): ?string
    {
        $s = mb_strtolower(trim((string) ($value ?? '')));
        if ($s === '') {
            return null;
        }
        if (str_contains($s, '€') || preg_match('/\beur(o|os)?\b/u', $s)) {
            return 'EUR';
        }
        if (preg_match('/\b(tnd|dt)\b/u', $s) || str_contains($s, 'د.ت')) {
            return 'TND';
        }
        if (str_contains($s, '$') || preg_match('/\busd\b/u', $s)) {
            return 'USD';
        }

        return null;
    }

    /**
     * Tax percentage cell to a 0-100 rate: "20.00%", "20,5 %", "20", "0.2" (fraction) => 20.00, 20.50, 20.00, 20.00.
     */
    public function parseTaxRate(mixed $value): ?float
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }
        $decimal = $this->parseDecimal($raw);
        if ($decimal === null) {
            return null;
        }
        $rate = (float) $decimal;
        if (! str_contains($raw, '%') && $rate > 0 && $rate < 1) {
            $rate *= 100;
        }
        if ($rate < 0 || $rate > 100) {
            return null;
        }

        return round($rate, 2);
    }

    /**
     * Parsed view of mapped product rows for the import preview (price, currency, tax, type).
     *
     * @param  list<array<string, mixed>>  $rows  rows already mapped to product field keys
     * @return list<array{name: string, kind: string, price: string, currency: string, tax_rate: ?float, tax_rate_2: ?float, unit: ?string, category: ?string, description_lines: int}>
     */
    public function previewProducts(Company $company, array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $sku = $this->nullableString(isset($row['sku']) ? (string) $row['sku'] : null, 64);
            $name = trim((string) ($row['name'] ?? '')) ?: (string) $sku;
            $priceRaw = $row['price'] ?? $row['base_price'] ?? null;
            $currency = flowdesk_normalize_currency_code($this->currencyFromText($row['currency'] ?? null) ?? $this->currencyFromAmountText($priceRaw) ?? $company->default_currency ?? flowdesk_default_currency());
            $stockRaw = $row['stock_qty'] ?? $row['stock'] ?? null;
            $hasStock = $stockRaw !== null && trim((string) $stockRaw) !== '';
            $classified = $this->classifyImportedProduct($row, $name, $sku, $hasStock);
            $kind = $classified['kind'];
            $minor = (int) ($this->amountToMinor($priceRaw, $currency) ?? 0);
            $description = trim((string) ($row['description'] ?? ''));
            $out[] = [
                'name' => $name,
                'kind' => $kind,
                'price' => flowdesk_format_minor($minor, $currency),
                'currency' => $currency,
                'tax_rate' => $this->parseTaxRate($row['tax_rate'] ?? null),
                'tax_rate_2' => $this->parseTaxRate($row['tax_rate_2'] ?? null),
                'unit' => $classified['unit'] ?: null,
                'category' => $this->nullableString(isset($row['category']) ? (string) $row['category'] : null, 100),
                'description_lines' => $description === '' ? 0 : substr_count($description, "\n") + 1,
            ];
        }

        return $out;
    }

    private function currencyFromText(mixed $value): ?string
    {
        $s = trim((string) ($value ?? ''));
        if ($s === '') {
            return null;
        }

        return match (mb_strtolower($s)) {
            '€', 'euro', 'euros', 'eur' => 'EUR',
            'dt', 'tnd', 'dinar', 'dinars', 'د.ت' => 'TND',
            '$', 'usd', 'dollar', 'dollars' => 'USD',
            default => strtoupper($s),
        };
    }

    private function normalizeEmail(mixed $email): ?string
    {
        if (! is_string($email)) {
            return null;
        }
        $email = strtolower(trim($email));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return $email;
    }

    private function nullableString(mixed $value, int $max): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $value = trim($value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    private function nullableDate(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    private function boolValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        $s = strtolower(trim((string) $value));

        return in_array($s, ['1', 'true', 'yes', 'y', 'on'], true);
    }
}
