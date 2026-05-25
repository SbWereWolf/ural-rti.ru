<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ProductIndexRequest extends FormRequest
{
    private const DEFAULT_PER_PAGE = 50;
    private const MAX_PER_PAGE = 100;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
            'category_id' => ['sometimes', 'integer', 'exists:category,id'],
            'price_min' => ['sometimes', 'numeric', 'min:0'],
            'price_max' => ['sometimes', 'numeric', 'min:0', 'gte:price_min'],
            'in_stock' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'page.integer' => 'Параметр page должен быть целым числом.',
            'page.min' => 'Параметр page должен быть больше или равен 1.',

            'per_page.integer' => 'Параметр per_page должен быть целым числом.',
            'per_page.min' => 'Параметр per_page должен быть больше или равен 1.',
            'per_page.max' => 'Параметр per_page не должен быть больше '.self::MAX_PER_PAGE.'.',

            'category_id.integer' => 'Параметр category_id должен быть целым числом.',
            'category_id.exists' => 'Категория с указанным category_id не найдена.',

            'price_min.numeric' => 'Параметр price_min должен быть числом.',
            'price_min.min' => 'Параметр price_min должен быть больше или равен 0.',

            'price_max.numeric' => 'Параметр price_max должен быть числом.',
            'price_max.min' => 'Параметр price_max должен быть больше или равен 0.',
            'price_max.gte' => 'Параметр price_max должен быть больше или равен price_min.',

            'in_stock.boolean' => 'Параметр in_stock должен быть булевым значением.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('in_stock')) {
            return;
        }

        $value = $this->input('in_stock');

        if ($value === 'true') {
            $this->merge(['in_stock' => true]);

            return;
        }

        if ($value === 'false') {
            $this->merge(['in_stock' => false]);
        }
    }

    protected function failedValidation(Validator $validator): void
    {
        $errors = [];

        foreach ($validator->errors()->messages() as $field => $messages) {
            $errors[$field] = array_map(
                fn (string $message): array => [
                    'message' => $message,
                    'suggestion' => $this->suggestionFor($field),
                ],
                $messages,
            );
        }

        throw new HttpResponseException(response()->json([
            'message' => 'Ошибка валидации. Исправьте параметры запроса из поля errors и повторите запрос.',
            'errors' => $errors,
        ], 422));
    }

    public function page(): int
    {
        return max(1, (int) $this->input('page', 1));
    }

    public function perPage(): int
    {
        return (int) $this->input('per_page', self::DEFAULT_PER_PAGE);
    }

    public function categoryId(): ?int
    {
        if (! $this->filled('category_id')) {
            return null;
        }

        return (int) $this->input('category_id');
    }

    public function priceMin(): ?string
    {
        if (! $this->filled('price_min')) {
            return null;
        }

        return (string) $this->input('price_min');
    }

    public function priceMax(): ?string
    {
        if (! $this->filled('price_max')) {
            return null;
        }

        return (string) $this->input('price_max');
    }

    public function inStock(): bool
    {
        return $this->boolean('in_stock');
    }

    public function hasEffectiveFilters(): bool
    {
        return $this->categoryId() !== null
            || $this->priceMin() !== null
            || $this->priceMax() !== null
            || $this->inStock();
    }

    private function suggestionFor(string $field): string
    {
        return match ($field) {
            'page' => 'Передайте page как целое число от 1, например page=1.',
            'per_page' => 'Передайте per_page=50 или другое целое число от 1 до '.self::MAX_PER_PAGE.'.',
            'category_id' => 'Передайте id существующей категории третьего уровня или уберите category_id, чтобы получить весь каталог.',
            'price_min' => 'Передайте неотрицательное число, например price_min=100.00.',
            'price_max' => 'Передайте неотрицательное число, которое больше или равно price_min, например price_max=500.00.',
            'in_stock' => 'Передайте in_stock=true, чтобы получить только товары в наличии, или in_stock=false, чтобы не фильтровать по остатку.',
            default => 'Удалите этот параметр или передайте значение, соответствующее контракту API.',
        };
    }
}
