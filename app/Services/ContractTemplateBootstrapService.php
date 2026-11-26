<?php

namespace App\Services;

use App\Models\Company;
use App\Models\ContractTemplate;

class ContractTemplateBootstrapService
{
    public function ensureDefaults(Company $company): void
    {
        $exists = ContractTemplate::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->exists();

        if ($exists) {
            return;
        }

        foreach ($this->defaultTemplates() as $index => $template) {
            ContractTemplate::query()->create([
                'company_id' => $company->id,
                'name' => $template['name'],
                'type_key' => $template['type_key'],
                'body_html' => $template['body_html'],
                'is_active' => true,
                'sort_order' => $index,
            ]);
        }
    }

    /**
     * @return list<array{name: string, type_key: string, body_html: string}>
     */
    public function defaultTemplates(): array
    {
        return [
            [
                'name' => __('contract_template_service'),
                'type_key' => 'service',
                'body_html' => $this->serviceBody(),
            ],
            [
                'name' => __('contract_template_nda'),
                'type_key' => 'nda',
                'body_html' => $this->ndaBody(),
            ],
            [
                'name' => __('contract_template_retainer'),
                'type_key' => 'retainer',
                'body_html' => $this->retainerBody(),
            ],
            [
                'name' => __('contract_template_consulting'),
                'type_key' => 'consulting',
                'body_html' => $this->consultingBody(),
            ],
        ];
    }

    private function serviceBody(): string
    {
        return <<<'HTML'
<h2>Service Agreement</h2>
<p>This Service Agreement (the "Agreement") is entered into as of {{today}} between <strong>{{company_name}}</strong> ("Provider"), located at {{company_address}}, and <strong>{{client_name}}</strong> ("Client"), email {{client_email}}, phone {{client_phone}}.</p>
<p><strong>Reference:</strong> {{contract_reference}} &nbsp;|&nbsp; <strong>Title:</strong> {{contract_title}}</p>
<p><strong>Term:</strong> from {{starts_on}} to {{ends_on}}.</p>
<h3>1. Scope of services</h3>
<p>Provider shall perform the professional services described in this Agreement and any attached statements of work.</p>
<h3>2. Fees and payment</h3>
<p>Fees, invoicing schedule, and payment terms are as agreed between the parties in writing.</p>
<h3>3. Confidentiality</h3>
<p>Each party shall keep confidential information received from the other party and use it only for performing under this Agreement.</p>
<h3>4. Termination</h3>
<p>Either party may terminate this Agreement according to the notice terms agreed in writing.</p>
<p>IN WITNESS WHEREOF, the parties have executed this Agreement as of the date first written above.</p>
HTML;
    }

    private function ndaBody(): string
    {
        return <<<'HTML'
<h2>Non-Disclosure Agreement</h2>
<p>This Non-Disclosure Agreement is made as of {{today}} between <strong>{{company_name}}</strong> ("Disclosing Party"), {{company_address}}, and <strong>{{client_name}}</strong> ("Receiving Party"), {{client_email}}.</p>
<p><strong>Reference:</strong> {{contract_reference}}</p>
<p><strong>Effective period:</strong> {{starts_on}} through {{ends_on}}.</p>
<h3>1. Definition of Confidential Information</h3>
<p>Confidential Information means non-public business, technical, and financial information disclosed by either party.</p>
<h3>2. Obligations</h3>
<p>The Receiving Party shall protect Confidential Information with reasonable care and shall not disclose it to third parties without prior written consent.</p>
<h3>3. Exceptions</h3>
<p>Obligations do not apply to information that is public, independently developed, or required to be disclosed by law.</p>
<p>Signed as of {{today}}.</p>
HTML;
    }

    private function retainerBody(): string
    {
        return <<<'HTML'
<h2>Retainer Agreement</h2>
<p>This Retainer Agreement is entered into as of {{today}} between <strong>{{company_name}}</strong> ("Provider"), {{company_address}}, and <strong>{{client_name}}</strong> ("Client"), {{client_email}} / {{client_phone}}.</p>
<p><strong>Reference:</strong> {{contract_reference}} &nbsp;|&nbsp; <strong>Title:</strong> {{contract_title}}</p>
<p><strong>Retainer period:</strong> {{starts_on}} to {{ends_on}}.</p>
<h3>1. Retainer services</h3>
<p>Provider will make available a defined monthly capacity of professional services during the retainer period.</p>
<h3>2. Retainer fee</h3>
<p>Client shall pay the agreed retainer fee according to the invoice schedule. Unused hours do not roll over unless agreed in writing.</p>
<h3>3. Additional work</h3>
<p>Work beyond the retainer capacity may be billed separately at agreed rates.</p>
<p>IN WITNESS WHEREOF, the parties sign this retainer as of {{today}}.</p>
HTML;
    }

    private function consultingBody(): string
    {
        return <<<'HTML'
<h2>Consulting Agreement</h2>
<p>This Consulting Agreement is made as of {{today}} between <strong>{{company_name}}</strong> ("Consultant"), {{company_address}}, and <strong>{{client_name}}</strong> ("Client"), {{client_email}}.</p>
<p><strong>Reference:</strong> {{contract_reference}} &nbsp;|&nbsp; <strong>Engagement:</strong> {{contract_title}}</p>
<p><strong>Engagement dates:</strong> {{starts_on}} – {{ends_on}}.</p>
<h3>1. Consulting services</h3>
<p>Consultant shall provide advisory and consulting services as described in this Agreement and related statements of work.</p>
<h3>2. Independent contractor</h3>
<p>Consultant is an independent contractor and not an employee of Client.</p>
<h3>3. Deliverables and IP</h3>
<p>Unless otherwise agreed, deliverables created specifically for Client under this engagement are assigned to Client upon full payment.</p>
<p>Signed on {{today}}.</p>
HTML;
    }
}
