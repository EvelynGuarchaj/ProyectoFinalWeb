// GET /api/yo: datos del asesor con sesión. No consulta la base: el middleware ya lo leyó.
import { Router } from 'express';

export const rutasYo = Router();

rutasYo.get('/', (req, res) => {
  res.json(req.usuario);
});
