<?php

declare(strict_types=1);

namespace App\Core\Contact\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validação server-side do formulário de contato da landing.
 *
 * Anti-spam em 3 camadas: honeypot (campo invisível — ver controller),
 * throttle:sensitive na rota (config/security.php) e esta validação.
 */
final class ContactRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'subject' => ['required', Rule::in(['suggestion', 'complaint', 'other'])],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            // Honeypot: invisível para humanos; bots o preenchem. "nullable"
            // aqui — o descarte silencioso acontece no controller.
            'website' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('contact.form.name'),
            'email' => __('contact.form.email'),
            'subject' => __('contact.form.subject'),
            'message' => __('contact.form.message'),
        ];
    }
}
