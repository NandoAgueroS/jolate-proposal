-- ============================================================
-- JOLATE 2026 — Lote de prueba para la tabla `inscriptos`
-- ============================================================
-- 60 registros ficticios: 30 Expositor (id_tipo_inscripto = 1)
--                        + 30 Asistente (id_tipo_inscripto = 2)
--
-- Este archivo NO se carga automáticamente (el deploy monta solo
-- docker/database/init.sql). Ejecutalo manualmente:
--
--   Opción A — phpMyAdmin:
--     1) Seleccioná la base `jolate` en el panel izquierdo.
--     2) Pestaña "Importar" (o "SQL") y pegá / subí este archivo.
--
--   Opción B — consola mysql:
--     mysql -u <usuario> -p jolate < seed-inscriptos.sql
--
-- NOTA: los expositores de este lote NO llevan PDF (archivo_filename
-- es NULL). La columna es nullable en el schema y el panel admin ya
-- oculta la descarga cuando está vacía.
--
-- Verificación opcional al terminar:
--   SELECT id_tipo_inscripto, COUNT(*) FROM `inscriptos` GROUP BY id_tipo_inscripto;
--   (debe devolver 1 → 30 y 2 → 30)
-- ============================================================

USE `jolate`;

INSERT INTO `inscriptos`
    (`id_tipo_inscripto`, `nombre`, `institucion`, `email`, `dni`,
     `titulo_ponencia`, `eje_tematico`, `archivo_filename`, `created_at`)
VALUES
    -- ======================= EXPOSITORES (30) =======================
    (1, 'Cecilia Ferreyra', 'Universidad Nacional de San Luis (UNSL), Argentina', 'cecilia.ferreyra@unsl.edu.ar', '28451236',
     'Equilibrio de Nash en mercados con información asimétrica y competencia imperfecta', 'Teoría de Juegos', NULL, '2026-05-12 10:23:00'),
    (1, 'Martín Aguirre', 'Universidad de Buenos Aires, Argentina', 'martin.aguirre@uba.ar', '30147852',
     'Preferencias sociales y reglas de agregación bajo restricciones de dominio', 'Elección Social', NULL, '2026-05-14 16:41:00'),
    (1, 'Roberto Cisneros', 'UNAM, México', 'roberto.cisneros@unam.mx', 'M12345678',
     'Dinámica de crecimiento con cambio tecnológico endógeno y heterogeneidad sectorial', 'Crecimiento Económico', NULL, '2026-05-16 09:05:00'),
    (1, 'Daniela Sosa', 'COLMEX, México', 'daniela.sosa@colmex.mx', 'A78945612',
     'Tributación óptima y provisión de bienes públicos en economías abiertas', 'Economía Pública', NULL, '2026-05-19 13:37:00'),
    (1, 'José María Altamirano', 'Universidad de La Punta (ULP), Argentina', 'jose.altamirano@ulp.edu.ar', '31987654',
     'Existencia y unicidad de equilibrio general con externalidades de red', 'Equilibrio General', NULL, '2026-05-22 11:12:00'),
    (1, 'Luana Pedreira', 'Universidade de São Paulo (USP), Brasil', 'luana.pedreira@usp.br', 'BP34567891',
     'Inestabilidad y bifurcaciones en modelos de crecimiento de dos sectores', 'Dinámica Económica', NULL, '2026-05-24 18:52:00'),
    (1, 'Andrés Quiroga', 'Universidad de los Andes, Colombia', 'a.quiroga@uniandes.edu.co', 'C45678901',
     'Aplicaciones de la teoría de juegos a la negociación climática internacional', 'Áreas Temáticas Afines', NULL, '2026-05-27 08:44:00'),
    (1, 'Florencia Duarte', 'Universidad Nacional de La Plata (UNLP), Argentina', 'florencia.duarte@unlp.edu.ar', '33221144',
     'Juegos de señalización y discriminación en el mercado laboral', 'Teoría de Juegos', NULL, '2026-05-29 15:20:00'),
    (1, 'Pablo Zubeldía', 'ITAM, México', 'pablo.zubeldia@itam.mx', 'I56789012',
     'Elección social y votación por aprobación: una aproximación experimental', 'Elección Social', NULL, '2026-06-01 12:08:00'),
    (1, 'Gabriela Ríos', 'Universidad de Chile, Chile', 'gabriela.rios@uchile.cl', 'CH67890123',
     'Desigualdad y crecimiento: evidencia empírica para América Latina', 'Crecimiento Económico', NULL, '2026-06-03 17:33:00'),
    (1, 'Hernán Bustamante', 'Universidad Nacional de Córdoba (UNC), Argentina', 'hernan.bustamante@unc.edu.ar', '29065431',
     'Impuestos Pigouvianos y externalidades en la producción de energía', 'Economía Pública', NULL, '2026-06-05 10:47:00'),
    (1, 'Marcela Oviedo', 'CINVESTAV, México', 'marcela.oviedo@cinvestav.mx', 'C78901234',
     'Equilibrio general computable con mercados incompletos y riesgo', 'Equilibrio General', NULL, '2026-06-08 14:56:00'),
    (1, 'Tomás Villanueva', 'Universidad de San Andrés (UdeSA), Argentina', 'tomas.villanueva@udesa.edu.ar', '31544897',
     'Ciclos económicos endógenos y comportamiento no lineal de la inversión', 'Dinámica Económica', NULL, '2026-06-10 09:28:00'),
    (1, 'Valentina Herrera', 'Universidad de la República, Uruguay', 'valentina.herrera@udelar.edu.uy', 'U89012345',
     'Teoría de juegos y cooperación en el uso de recursos comunes', 'Áreas Temáticas Afines', NULL, '2026-06-12 19:14:00'),
    (1, 'Sebastián Lucero', 'Universidad Nacional de San Luis (UNSL), Argentina', 'sebastian.lucero@unsl.edu.ar', '27110987',
     'Subastas de espectro y colusión implícita entre postores', 'Teoría de Juegos', NULL, '2026-06-15 11:36:00'),
    (1, 'Laura Camargo', 'Universidad Autónoma de San Luis Potosí (UASLP), México', 'laura.camargo@uaslp.mx', 'S90123456',
     'Imposibilidades en la agregación de preferencias con alternativas heterogéneas', 'Elección Social', NULL, '2026-06-17 08:19:00'),
    (1, 'Carlos Fuentes', 'UNICAMP, Brasil', 'carlos.fuentes@unicamp.br', 'BF01234567',
     'Crecimiento económico y transición demográfica en economías emergentes', 'Crecimiento Económico', NULL, '2026-06-19 16:02:00'),
    (1, 'Natalia Pereyra', 'FLACSO, Argentina', 'natalia.pereyra@flacso.org.ar', '35881245',
     'Federalismo fiscal y competencia tributaria entre jurisdicciones', 'Economía Pública', NULL, '2026-06-22 13:45:00'),
    (1, 'Gustavo Medina', 'Universidad Nacional de Colombia, Colombia', 'gustavo.medina@unal.edu.co', 'C12345678',
     'Equilibrio general con preferencias no transitivas y racionalidad acotada', 'Equilibrio General', NULL, '2026-06-24 10:09:00'),
    (1, 'Andrea Kowalski', 'Pontificia Universidad Católica de Chile, Chile', 'andrea.kowalski@uc.cl', 'CH23456789',
     'Comportamiento caótico en modelos de crecimiento endógeno', 'Dinámica Económica', NULL, '2026-06-26 15:58:00'),
    (1, 'Rodrigo Méndez', 'Universidad de Buenos Aires, Argentina', 'rodrigo.mendez@uba.ar', '32665477',
     'Mecanismos de asignación y teoría de la implementación aplicada a servicios de salud', 'Áreas Temáticas Afines', NULL, '2026-06-29 12:31:00'),
    (1, 'Paula Giménez', 'Universidad Nacional del Sur (UNS), Argentina', 'paula.gimenez@uns.edu.ar', '27987432',
     'Juegos repetidos y mantenimiento de carteles en mercados concentrados', 'Teoría de Juegos', NULL, '2026-07-01 09:47:00'),
    (1, 'Ignacio Peña', 'COLMEX, México', 'ignacio.pena@colmex.mx', 'A34567890',
     'Bienestar social y medición de la pobreza multidimensional', 'Elección Social', NULL, '2026-07-03 17:05:00'),
    (1, 'Sofía Braga', 'Universidade de São Paulo (USP), Brasil', 'sofia.braga@usp.br', 'BP45678901',
     'Innovación, patentes y crecimiento: un modelo de variedades con spillovers', 'Crecimiento Económico', NULL, '2026-07-06 11:54:00'),
    (1, 'Emmanuel Cabrera', 'Universidad Torcuato Di Tella (UTDT), Argentina', 'ecabrera@utdt.edu', '34001256',
     'Gasto público y política fiscal en el ciclo: reglas versus discrecionalidad', 'Economía Pública', NULL, '2026-07-08 14:22:00'),
    (1, 'Julieta Fonseca', 'UAM, México', 'julieta.fonseca@uam.mx', 'M56789012',
     'Equilibrio general con información asimétrica en mercados de crédito', 'Equilibrio General', NULL, '2026-07-10 08:37:00'),
    (1, 'Diego Montero', 'Universidad de Guanajuato, México', 'diego.montero@ugto.mx', 'G67890123',
     'Expectativas heterogéneas y dinámica de precios de activos', 'Dinámica Económica', NULL, '2026-07-13 16:48:00'),
    (1, 'María de los Ángeles Ochoa', 'Pontificia Universidad Católica del Perú, Perú', 'maria.ochoa@pucp.edu.pe', 'P78901234',
     'Economía del comportamiento y diseño de políticas contra la pobreza energética', 'Áreas Temáticas Afines', NULL, '2026-07-15 10:26:00'),
    (1, 'Federico Roldán', 'Universidad Nacional de Cuyo (UNCuyo), Argentina', 'federico.roldan@uncuyo.edu.ar', '30123654',
     'Diseño de mecanismos para la asignación de becas y cuotas universitarias', 'Teoría de Juegos', NULL, '2026-07-17 13:03:00'),
    (1, 'Ximena Delgado', 'Universidad del Rosario, Colombia', 'ximena.delgado@urosario.edu.co', 'C89012345',
     'Teoría de la elección social aplicada a la representación proporcional', 'Elección Social', NULL, '2026-07-20 09:55:00'),

    -- ======================= ASISTENTES (30) =======================
    (2, 'Agustín Vera', 'Universidad Nacional de San Luis (UNSL), Argentina', 'agustin.vera@unsl.edu.ar', '31098765',
     NULL, NULL, NULL, '2026-05-13 10:15:00'),
    (2, 'Milagros Soria', 'Universidad de La Punta (ULP), Argentina', 'milagros.soria@ulp.edu.ar', '32045518',
     NULL, NULL, NULL, '2026-05-15 14:30:00'),
    (2, 'Thiago Almeida', 'Universidade de São Paulo (USP), Brasil', 'thiago.almeida@usp.br', 'BP56789012',
     NULL, NULL, NULL, '2026-05-18 09:40:00'),
    (2, 'Camila Rodríguez', 'UNAM, México', 'camila.rodriguez@unam.mx', 'M67890123',
     NULL, NULL, NULL, '2026-05-20 11:22:00'),
    (2, 'Bruno Fassi', 'Universidad de Buenos Aires, Argentina', 'bruno.fassi@uba.ar', '29456783',
     NULL, NULL, NULL, '2026-05-23 16:05:00'),
    (2, 'Renata Costa', 'UNICAMP, Brasil', 'renata.costa@unicamp.br', 'BF12345678',
     NULL, NULL, NULL, '2026-05-26 08:55:00'),
    (2, 'Leandro Molina', 'Universidad de Chile, Chile', 'leandro.molina@uchile.cl', 'CH34567890',
     NULL, NULL, NULL, '2026-05-28 13:18:00'),
    (2, 'Abril Quinteros', 'Universidad Nacional de La Plata (UNLP), Argentina', 'abril.quinteros@unlp.edu.ar', '35221478',
     NULL, NULL, NULL, '2026-05-31 10:02:00'),
    (2, 'Joaquín Sandoval', 'COLMEX, México', 'joaquin.sandoval@colmex.mx', 'A45678901',
     NULL, NULL, NULL, '2026-06-02 15:44:00'),
    (2, 'Francisca Vergara', 'Pontificia Universidad Católica de Chile, Chile', 'francisca.vergara@uc.cl', 'CH45678901',
     NULL, NULL, NULL, '2026-06-04 09:12:00'),
    (2, 'Nicolás Ojeda', 'Universidad Nacional de Córdoba (UNC), Argentina', 'nicolas.ojeda@unc.edu.ar', '30567890',
     NULL, NULL, NULL, '2026-06-07 12:26:00'),
    (2, 'Tatiana Espejo', 'Universidad de los Andes, Colombia', 'tatiana.espejo@uniandes.edu.co', 'C56789012',
     NULL, NULL, NULL, '2026-06-09 17:31:00'),
    (2, 'Gonzalo Pereyra', 'Universidad Nacional del Sur (UNS), Argentina', 'gonzalo.pereyra@uns.edu.ar', '28987654',
     NULL, NULL, NULL, '2026-06-11 08:48:00'),
    (2, 'Martina Colombo', 'Universidad de San Andrés (UdeSA), Argentina', 'martina.colombo@udesa.edu.ar', '33897654',
     NULL, NULL, NULL, '2026-06-14 14:07:00'),
    (2, 'Santiago Basualdo', 'ITAM, México', 'santiago.basualdo@itam.mx', 'I67890123',
     NULL, NULL, NULL, '2026-06-16 10:39:00'),
    (2, 'Lucía Maldonado', 'Universidad de la República, Uruguay', 'lucia.maldonado@udelar.edu.uy', 'U90123456',
     NULL, NULL, NULL, '2026-06-18 16:23:00'),
    (2, 'Iván Cáceres', 'CINVESTAV, México', 'ivan.caceres@cinvestav.mx', 'C90123456',
     NULL, NULL, NULL, '2026-06-21 09:56:00'),
    (2, 'Malena Castro', 'Universidad Autónoma de San Luis Potosí (UASLP), México', 'malena.castro@uaslp.mx', 'S01234567',
     NULL, NULL, NULL, '2026-06-23 13:40:00'),
    (2, 'Facundo Brusa', 'Universidad Torcuato Di Tella (UTDT), Argentina', 'fbrusa@utdt.edu', '32111234',
     NULL, NULL, NULL, '2026-06-25 11:08:00'),
    (2, 'Belén Argüello', 'Universidad Nacional de Colombia, Colombia', 'belen.arguello@unal.edu.co', 'C01234567',
     NULL, NULL, NULL, '2026-06-28 15:16:00'),
    (2, 'Matías Riquelme', 'Universidad de Chile, Chile', 'matias.riquelme@uchile.cl', 'CH56789012',
     NULL, NULL, NULL, '2026-06-30 08:29:00'),
    (2, 'Camila Benítez', 'FLACSO, Argentina', 'camila.benitez@flacso.org.ar', '34443210',
     NULL, NULL, NULL, '2026-07-02 12:53:00'),
    (2, 'Raúl Coronel', 'Universidad de Guanajuato, México', 'raul.coronel@ugto.mx', 'G78901234',
     NULL, NULL, NULL, '2026-07-05 10:47:00'),
    (2, 'Eugenia Varela', 'Pontificia Universidad Católica del Perú, Perú', 'eugenia.varela@pucp.edu.pe', 'P89012345',
     NULL, NULL, NULL, '2026-07-07 17:20:00'),
    (2, 'Damián Herrera', 'Universidad Nacional de Cuyo (UNCuyo), Argentina', 'damian.herrera@uncuyo.edu.ar', '29876012',
     NULL, NULL, NULL, '2026-07-09 09:33:00'),
    (2, 'Nataly Acuña', 'Universidade de São Paulo (USP), Brasil', 'nataly.acuna@usp.br', 'BP67890123',
     NULL, NULL, NULL, '2026-07-11 14:58:00'),
    (2, 'Tomás Bustos', 'Universidad Nacional de San Luis (UNSL), Argentina', 'tomas.bustos@unsl.edu.ar', '31234560',
     NULL, NULL, NULL, '2026-07-14 11:12:00'),
    (2, 'Julieta Marengo', 'UAM, México', 'julieta.marengo@uam.mx', 'M78901234',
     NULL, NULL, NULL, '2026-07-16 16:40:00'),
    (2, 'Emiliano Robles', 'Universidad del Rosario, Colombia', 'emiliano.robles@urosario.edu.co', 'C12345690',
     NULL, NULL, NULL, '2026-07-19 08:36:00'),
    (2, 'Paula Gutiérrez', 'Universidad de Buenos Aires, Argentina', 'paula.gutierrez@uba.ar', '36554721',
     NULL, NULL, NULL, '2026-07-22 13:25:00');
