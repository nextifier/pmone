<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AiChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:10000'],
            'conversation_id' => ['nullable', 'string', 'exists:agent_conversations,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'message.required' => 'Message is required.',
            'message.max' => 'Message cannot exceed 10,000 characters.',
            'conversation_id.exists' => 'Conversation not found.',
        ];
    }
}
