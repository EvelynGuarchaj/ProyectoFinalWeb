<?php

/*
|--------------------------------------------------------------------------
| Mensajes de validación en español, de usted (L7, spec 16)
|--------------------------------------------------------------------------
|
| Mismas claves que lang/en/validation.php (ValidacionTest lo comprueba).
| Los FormRequest del panel tienen sus propios messages(); este archivo es
| la red para cualquier regla que no tenga mensaje propio.
|
*/

return [

    'accepted' => 'Debe aceptar el campo :attribute.',
    'accepted_if' => 'Debe aceptar el campo :attribute cuando :other es :value.',
    'active_url' => 'Escriba una dirección web válida en :attribute.',
    'after' => 'El campo :attribute debe ser una fecha posterior a :date.',
    'after_or_equal' => 'El campo :attribute debe ser una fecha igual o posterior a :date.',
    'alpha' => 'El campo :attribute solo puede contener letras.',
    'alpha_dash' => 'El campo :attribute solo puede contener letras, números, guiones y guiones bajos.',
    'alpha_num' => 'El campo :attribute solo puede contener letras y números.',
    'any_of' => 'El valor de :attribute no es válido.',
    'array' => 'El campo :attribute debe ser una lista.',
    'array_keys' => 'El campo :attribute solo puede contener estas claves: :values.',
    'ascii' => 'El campo :attribute solo puede contener letras, números y símbolos simples.',
    'base64' => 'El campo :attribute debe ser un texto Base64 válido.',
    'before' => 'El campo :attribute debe ser una fecha anterior a :date.',
    'before_or_equal' => 'El campo :attribute debe ser una fecha igual o anterior a :date.',
    'between' => [
        'array' => 'El campo :attribute debe tener entre :min y :max elementos.',
        'file' => 'El archivo :attribute debe pesar entre :min y :max kilobytes.',
        'numeric' => 'El campo :attribute debe estar entre :min y :max.',
        'string' => 'El campo :attribute debe tener entre :min y :max caracteres.',
    ],
    'boolean' => 'Indique un valor válido para :attribute.',
    'can' => 'El campo :attribute tiene un valor que no está permitido.',
    'confirmed' => 'La confirmación de :attribute no coincide.',
    'contains' => 'Al campo :attribute le falta un valor obligatorio.',
    'current_password' => 'La contraseña no es correcta.',
    'date' => 'Escriba una fecha válida en :attribute.',
    'date_equals' => 'El campo :attribute debe ser la fecha :date.',
    'date_format' => 'Escriba :attribute con el formato :format.',
    'decimal' => 'El campo :attribute debe tener :decimal decimales.',
    'declined' => 'Debe rechazar el campo :attribute.',
    'declined_if' => 'Debe rechazar el campo :attribute cuando :other es :value.',
    'different' => 'Los campos :attribute y :other deben ser distintos.',
    'digits' => 'El campo :attribute debe tener :digits dígitos.',
    'digits_between' => 'El campo :attribute debe tener entre :min y :max dígitos.',
    'dimensions' => 'La imagen :attribute no tiene un tamaño válido.',
    'distinct' => 'El campo :attribute tiene un valor repetido.',
    'doesnt_contain' => 'El campo :attribute no puede contener ninguno de estos valores: :values.',
    'doesnt_end_with' => 'El campo :attribute no puede terminar con: :values.',
    'doesnt_start_with' => 'El campo :attribute no puede empezar con: :values.',
    'email' => 'Escriba un :attribute válido.',
    'encoding' => 'El campo :attribute debe estar codificado en :encoding.',
    'ends_with' => 'El campo :attribute debe terminar con uno de estos valores: :values.',
    'enum' => 'Elija un :attribute válido de la lista.',
    'exists' => 'Elija un :attribute válido de la lista.',
    'extensions' => 'El archivo :attribute debe tener una de estas extensiones: :values.',
    'file' => 'El campo :attribute debe ser un archivo.',
    'filled' => 'Complete el campo :attribute.',
    'gt' => [
        'array' => 'El campo :attribute debe tener más de :value elementos.',
        'file' => 'El archivo :attribute debe pesar más de :value kilobytes.',
        'numeric' => 'El campo :attribute debe ser mayor que :value.',
        'string' => 'El campo :attribute debe tener más de :value caracteres.',
    ],
    'gte' => [
        'array' => 'El campo :attribute debe tener :value elementos o más.',
        'file' => 'El archivo :attribute debe pesar :value kilobytes o más.',
        'numeric' => 'El campo :attribute debe ser mayor o igual que :value.',
        'string' => 'El campo :attribute debe tener :value caracteres o más.',
    ],
    'hex_color' => 'Escriba un color hexadecimal válido en :attribute.',
    'image' => 'El campo :attribute debe ser una imagen.',
    'in' => 'Elija un :attribute válido de la lista.',
    'in_array' => 'El campo :attribute debe estar en :other.',
    'in_array_keys' => 'El campo :attribute debe contener al menos una de estas claves: :values.',
    'integer' => 'El campo :attribute debe ser un número entero.',
    'ip' => 'Escriba una dirección IP válida en :attribute.',
    'ipv4' => 'Escriba una dirección IPv4 válida en :attribute.',
    'ipv6' => 'Escriba una dirección IPv6 válida en :attribute.',
    'json' => 'El campo :attribute debe ser un texto JSON válido.',
    'list' => 'El campo :attribute debe ser una lista.',
    'lowercase' => 'El campo :attribute debe estar en minúsculas.',
    'lt' => [
        'array' => 'El campo :attribute debe tener menos de :value elementos.',
        'file' => 'El archivo :attribute debe pesar menos de :value kilobytes.',
        'numeric' => 'El campo :attribute debe ser menor que :value.',
        'string' => 'El campo :attribute debe tener menos de :value caracteres.',
    ],
    'lte' => [
        'array' => 'El campo :attribute no puede tener más de :value elementos.',
        'file' => 'El archivo :attribute debe pesar :value kilobytes o menos.',
        'numeric' => 'El campo :attribute debe ser menor o igual que :value.',
        'string' => 'El campo :attribute debe tener :value caracteres o menos.',
    ],
    'mac_address' => 'Escriba una dirección MAC válida en :attribute.',
    'max' => [
        'array' => 'El campo :attribute no puede tener más de :max elementos.',
        'file' => 'El archivo :attribute no puede pesar más de :max kilobytes.',
        'numeric' => 'El campo :attribute no puede ser mayor que :max.',
        'string' => 'El campo :attribute no puede pasar de :max caracteres.',
    ],
    'max_digits' => 'El campo :attribute no puede tener más de :max dígitos.',
    'mimes' => 'El archivo :attribute debe ser de tipo: :values.',
    'mimetypes' => 'El archivo :attribute debe ser de tipo: :values.',
    'min' => [
        'array' => 'El campo :attribute debe tener al menos :min elementos.',
        'file' => 'El archivo :attribute debe pesar al menos :min kilobytes.',
        'numeric' => 'El campo :attribute debe ser al menos :min.',
        'string' => 'El campo :attribute debe tener al menos :min caracteres.',
    ],
    'min_digits' => 'El campo :attribute debe tener al menos :min dígitos.',
    'missing' => 'El campo :attribute no debe enviarse.',
    'missing_if' => 'El campo :attribute no debe enviarse cuando :other es :value.',
    'missing_unless' => 'El campo :attribute no debe enviarse salvo que :other sea :value.',
    'missing_with' => 'El campo :attribute no debe enviarse cuando :values está presente.',
    'missing_with_all' => 'El campo :attribute no debe enviarse cuando :values están presentes.',
    'multiple_of' => 'El campo :attribute debe ser múltiplo de :value.',
    'not_in' => 'Elija un :attribute válido de la lista.',
    'not_regex' => 'El formato de :attribute no es válido.',
    'numeric' => 'Escriba :attribute como un número.',
    'password' => [
        'letters' => 'La :attribute debe tener al menos una letra.',
        'mixed' => 'La :attribute debe tener al menos una mayúscula y una minúscula.',
        'numbers' => 'La :attribute debe tener al menos un número.',
        'symbols' => 'La :attribute debe tener al menos un símbolo.',
        'uncompromised' => 'Esa :attribute apareció en una filtración de datos. Elija otra.',
    ],
    'present' => 'El campo :attribute debe enviarse.',
    'present_if' => 'El campo :attribute debe enviarse cuando :other es :value.',
    'present_unless' => 'El campo :attribute debe enviarse salvo que :other sea :value.',
    'present_with' => 'El campo :attribute debe enviarse cuando :values está presente.',
    'present_with_all' => 'El campo :attribute debe enviarse cuando :values están presentes.',
    'prohibited' => 'El campo :attribute no está permitido.',
    'prohibited_if' => 'El campo :attribute no está permitido cuando :other es :value.',
    'prohibited_if_accepted' => 'El campo :attribute no está permitido cuando acepta :other.',
    'prohibited_if_declined' => 'El campo :attribute no está permitido cuando rechaza :other.',
    'prohibited_unless' => 'El campo :attribute solo está permitido cuando :other es uno de: :values.',
    'prohibits' => 'Si llena :attribute, deje vacío :other.',
    'regex' => 'El formato de :attribute no es válido.',
    'required' => 'Complete el campo :attribute.',
    'required_array_keys' => 'El campo :attribute debe contener: :values.',
    'required_if' => 'Complete el campo :attribute cuando :other es :value.',
    'required_if_accepted' => 'Complete el campo :attribute cuando acepta :other.',
    'required_if_declined' => 'Complete el campo :attribute cuando rechaza :other.',
    'required_unless' => 'Complete el campo :attribute salvo que :other sea uno de: :values.',
    'required_with' => 'Complete el campo :attribute cuando llena :values.',
    'required_with_all' => 'Complete el campo :attribute cuando llena :values.',
    'required_without' => 'Complete el campo :attribute cuando deja vacío :values.',
    'required_without_all' => 'Complete el campo :attribute cuando deja vacíos :values.',
    'same' => 'Los campos :attribute y :other deben coincidir.',
    'size' => [
        'array' => 'El campo :attribute debe tener :size elementos.',
        'file' => 'El archivo :attribute debe pesar :size kilobytes.',
        'numeric' => 'El campo :attribute debe ser :size.',
        'string' => 'El campo :attribute debe tener :size caracteres.',
    ],
    'starts_with' => 'El campo :attribute debe empezar con uno de estos valores: :values.',
    'string' => 'Escriba un texto válido en :attribute.',
    'timezone' => 'Elija una zona horaria válida en :attribute.',
    'unique' => 'Ya existe un registro con ese :attribute.',
    'uploaded' => 'No se pudo subir el archivo :attribute. Intente de nuevo.',
    'uppercase' => 'El campo :attribute debe estar en mayúsculas.',
    'url' => 'Escriba una dirección web válida en :attribute.',
    'ulid' => 'El campo :attribute debe ser un ULID válido.',
    'uuid' => 'El campo :attribute debe ser un UUID válido.',

    /*
    |--------------------------------------------------------------------------
    | Mensajes por campo
    |--------------------------------------------------------------------------
    |
    | Vacío a propósito: los mensajes propios de cada campo están en los
    | messages() de cada FormRequest (spec 16, decisión D1).
    |
    */

    'custom' => [],

    /*
    |--------------------------------------------------------------------------
    | Nombres de los campos en pantalla
    |--------------------------------------------------------------------------
    */

    'attributes' => [
        'name' => 'nombre',
        'nombre' => 'nombre',
        'email' => 'correo',
        'correo' => 'correo',
        'password' => 'contraseña',
        'password_confirmation' => 'confirmación de la contraseña',
        'current_password' => 'contraseña actual',
        'nit' => 'NIT',
        'telefono' => 'teléfono',
        'precio' => 'precio',
        'rol' => 'rol',
        'activo' => 'estado',
        'requiere_evaluacion' => 'requiere evaluación',
        'supervisor_id' => 'coordinador',
        'vendedor_id' => 'asesor',
    ],

];
