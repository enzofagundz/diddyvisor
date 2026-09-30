<?php

return [
    'accepted' => 'Aceite o campo :attribute.',
    'array' => 'O campo :attribute deve ser uma lista.',
    'boolean' => 'O campo :attribute deve ser verdadeiro ou falso.',
    'confirmed' => 'A confirmação de :attribute não confere.',
    'date' => 'Informe uma data válida em :attribute.',
    'date_format' => 'O campo :attribute deve seguir o formato :format.',
    'distinct' => 'O campo :attribute contém valores repetidos.',
    'email' => 'Informe um e-mail válido.',
    'exists' => 'O valor selecionado para :attribute não existe.',
    'integer' => 'O campo :attribute deve ser um número inteiro.',
    'in' => 'O valor selecionado para :attribute é inválido.',
    'max' => [
        'string' => 'O campo :attribute deve ter no máximo :max caracteres.',
        'array' => 'O campo :attribute deve ter no máximo :max itens.',
        'numeric' => 'O campo :attribute deve ser no máximo :max.',
    ],
    'min' => [
        'string' => 'O campo :attribute deve ter pelo menos :min caracteres.',
        'array' => 'O campo :attribute deve ter pelo menos :min item.',
        'numeric' => 'O campo :attribute deve ser pelo menos :min.',
    ],
    'present' => 'O campo :attribute deve estar presente.',
    'regex' => 'O formato de :attribute é inválido.',
    'required' => 'Preencha o campo :attribute.',
    'same' => 'Os campos :attribute e :other devem ser iguais.',
    'size' => ['string' => 'O campo :attribute deve ter :size caracteres.'],
    'string' => 'O campo :attribute deve ser texto.',
    'unique' => 'O campo :attribute já está em uso.',
    'attributes' => [
        'name' => 'nome', 'email' => 'e-mail', 'password' => 'senha',
        'passwordConfirmation' => 'confirmação de senha', 'total' => 'valor total',
        'participants' => 'participantes', 'shares' => 'partes', 'due_date' => 'vencimento',
    ],
];
