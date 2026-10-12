<?php

return [
    'accepted'        => 'Debes aceptar :attribute.',
    'array'           => ':Attribute debe ser una lista.',
    'after_or_equal'  => ':Attribute debe ser una fecha igual o posterior a :date.',
    'before_or_equal' => ':Attribute debe ser una fecha igual o anterior a :date.',
    'between'         => [
        'numeric' => ':Attribute debe estar entre :min y :max.',
        'string'  => ':Attribute debe tener entre :min y :max caracteres.',
        'array'   => ':Attribute debe tener entre :min y :max elementos.',
    ],
    'boolean'         => ':Attribute debe ser verdadero o falso.',
    'confirmed'       => 'La confirmación de :attribute no coincide.',
    'date'            => ':Attribute no es una fecha válida.',
    'date_format'     => ':Attribute no tiene el formato :format.',
    'email'           => ':Attribute no es un correo válido.',
    'exists'          => ':Attribute seleccionado no es válido.',
    'in'              => ':Attribute seleccionado no es válido.',
    'integer'         => ':Attribute debe ser un número entero.',
    'max'             => [
        'numeric' => ':Attribute no puede ser mayor que :max.',
        'string'  => ':Attribute no puede tener más de :max caracteres.',
        'array'   => ':Attribute no puede tener más de :max elementos.',
    ],
    'min'             => [
        'numeric' => ':Attribute debe ser al menos :min.',
        'string'  => ':Attribute debe tener al menos :min caracteres.',
        'array'   => ':Attribute debe tener al menos :min elementos.',
    ],
    'numeric'         => ':Attribute debe ser un número.',
    'regex'           => 'El formato de :attribute no es válido.',
    'required'        => 'El campo :attribute es obligatorio.',
    'size'            => [
        'numeric' => ':Attribute debe ser :size.',
        'string'  => ':Attribute debe tener :size caracteres.',
        'array'   => ':Attribute debe tener :size elementos.',
    ],
    'string'          => ':Attribute debe ser texto.',
    'unique'          => ':Attribute ya está registrado.',

    'password' => [
        'letters'       => 'La contraseña debe incluir al menos una letra.',
        'mixed'         => 'La contraseña debe tener mayúsculas y minúsculas.',
        'numbers'       => 'La contraseña debe incluir al menos un número.',
        'symbols'       => 'La contraseña debe incluir al menos un símbolo.',
        'uncompromised' => 'Esa contraseña apareció en filtraciones de datos. Elige otra.',
    ],

    'custom'     => [],
    'attributes' => [
        'name'     => 'nombre',
        'email'    => 'correo',
        'password' => 'contraseña',
    ],
];
