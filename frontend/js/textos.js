// Textos de pantalla (vocabulario de la clínica). Los valores del contrato no cambian.

export const ETAPAS = {
  prospecto: 'Interesado',
  calificado: 'Evaluado',
  propuesta: 'Presupuesto enviado',
  negociacion: 'En negociación',
  ganada: 'Aceptado',
  perdida: 'Rechazado'
};

export const ABIERTAS = ['prospecto', 'calificado', 'propuesta', 'negociacion'];

export const CERRADAS = ['ganada', 'perdida'];

// En porcentaje, la misma escala que el campo `probabilidad` de la API.
export const PROBABILIDAD = {
  prospecto: 10,
  calificado: 25,
  propuesta: 50,
  negociacion: 75,
  ganada: 100,
  perdida: 0
};

export const TIPOS = {
  llamada: 'Llamada',
  reunion: 'Cita',
  tarea: 'Tarea'
};
