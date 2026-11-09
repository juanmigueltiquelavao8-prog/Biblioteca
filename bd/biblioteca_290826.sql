-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost:3306
-- Tiempo de generación: 29-08-2026 a las 15:18:45
-- Versión del servidor: 8.4.3
-- Versión de PHP: 8.3.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `biblioteca`
--
CREATE DATABASE IF NOT EXISTS `biblioteca` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
USE `biblioteca`;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `prestamos`
--

CREATE TABLE `prestamos` (
  `id_prestamo` int NOT NULL,
  `id_usuario` int NOT NULL,
  `id_recurso` int NOT NULL,
  `fecha_prestamo` date NOT NULL,
  `fecha_limite` date NOT NULL,
  `fecha_devolucion` date DEFAULT NULL,
  `estado_prestamo` enum('Activo','Devuelto','Mora') DEFAULT 'Activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `prestamos`
--

INSERT INTO `prestamos` (`id_prestamo`, `id_usuario`, `id_recurso`, `fecha_prestamo`, `fecha_limite`, `fecha_devolucion`, `estado_prestamo`) VALUES
(1, 4, 1, '2026-08-01', '2026-08-08', '2026-08-10', 'Devuelto'),
(2, 3, 3, '2026-08-25', '2026-09-02', NULL, 'Activo'),
(3, 2, 5, '2026-08-29', '2026-08-29', NULL, 'Activo'),
(4, 2, 4, '2026-08-28', '2026-08-28', '2026-08-28', 'Devuelto'),
(5, 5, 3, '2026-08-29', '2026-08-31', NULL, 'Activo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `recursos`
--

CREATE TABLE `recursos` (
  `id_recurso` int NOT NULL,
  `codigo_inventario` varchar(30) NOT NULL,
  `titulo_nombre` varchar(200) NOT NULL,
  `tipo_recurso` enum('Libro','Equipo_Audiovisual','Accesorio') NOT NULL,
  `autor` varchar(100) DEFAULT NULL,
  `editorial` varchar(100) DEFAULT NULL,
  `anio_publicacion` int DEFAULT NULL,
  `isbn` varchar(20) DEFAULT NULL,
  `caracteristicas` text,
  `estado` enum('Disponible','Prestado','Mantenimiento','Dado_Baja') DEFAULT 'Disponible'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `recursos`
--

INSERT INTO `recursos` (`id_recurso`, `codigo_inventario`, `titulo_nombre`, `tipo_recurso`, `autor`, `editorial`, `anio_publicacion`, `isbn`, `caracteristicas`, `estado`) VALUES
(1, 'LIB-001', '100 años de soledad', 'Libro', 'Gabriel García Márquez', 'Debolsillo', 2024, '9786287745131', 'Idioma: Español, Páginas: 496, Encuadernación: Tapa Dura, Dimensiones: 22x14 cm', 'Dado_Baja'),
(2, 'LIB-002', 'Lógica de Programación', 'Libro', 'Omar Iván Trejos', 'Ediciones de la U', 2017, '9789587627237', 'Idioma: Español, Páginas: 300, Tapa Blanda', 'Disponible'),
(3, 'LIB-003', 'Cien años de soledad (Copia 2)', 'Libro', 'Gabriel García Márquez', 'Debolsillo', 2024, '9786287745131', 'Idioma: Español, Páginas: 496, Encuadernación: Tapa Dura', 'Prestado'),
(4, 'EQP-001', 'Videobeam Epson PowerLite', 'Equipo_Audiovisual', NULL, NULL, NULL, NULL, 'Resolución HD, Incluye cable de poder, Color Blanco', 'Disponible'),
(5, 'ACC-001', 'Cable HDMI 5 metros', 'Accesorio', NULL, NULL, NULL, NULL, 'Cable reforzado, 5 metros, puntas doradas', 'Prestado'),
(6, 'ACC-002', 'Control Remoto TV Samsung', 'Accesorio', NULL, NULL, NULL, NULL, 'Baterías AAA incluidas, para TV de la sala de audiovisuales', 'Mantenimiento');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sanciones`
--

CREATE TABLE `sanciones` (
  `id_sancion` int NOT NULL,
  `id_prestamo` int NOT NULL,
  `motivo` varchar(255) NOT NULL,
  `especificacion_reemplazo` text NOT NULL,
  `estado_sancion` enum('Pendiente_Reemplazo','Reemplazado_Resuelto') DEFAULT 'Pendiente_Reemplazo',
  `fecha_reporte` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `sanciones`
--

INSERT INTO `sanciones` (`id_sancion`, `id_prestamo`, `motivo`, `especificacion_reemplazo`, `estado_sancion`, `fecha_reporte`) VALUES
(1, 1, 'No entregó el libro físico al finalizar la fecha y reportó pérdida.', 'Libro Físico - Autor: Gabriel García Márquez - Editorial: Debolsillo - Año: 2024 - Idioma: Español - N° páginas: 496 - Encuadernación: Tapa Dura - Dimensiones: 22x14 cm - ISBN13: 9786287745131', 'Pendiente_Reemplazo', '2026-08-10');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id_usuario` int NOT NULL,
  `documento` varchar(20) NOT NULL,
  `nombre_completo` varchar(150) NOT NULL,
  `correo` varchar(60) NOT NULL,
  `rol` enum('Estudiante','Docente','Administrador') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'Estudiante',
  `contraseña` varchar(255) NOT NULL,
  `paz_y_salvo` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id_usuario`, `documento`, `nombre_completo`, `correo`, `rol`, `contraseña`, `paz_y_salvo`) VALUES
(1, '1000111222', 'Sandra Pachón', 'sandrap@gmail.com', 'Administrador', '$2y$10$wO3r2i52oJzP9E8oDqzU3O4Uj3F5/Xw2eBwO1T6Y/h6l2qR7C/tZq', 1),
(2, '55666777', 'Nury Bonilla', 'nuryb@hotmail.com', 'Docente', '$2y$10$wO3r2i52oJzP9E8oDqzU3O4Uj3F5/Xw2eBwO1T6Y/h6l2qR7C/tZq', 1),
(3, '1098765432', 'Diego Arroyo', 'diegoa@gmail.com', 'Estudiante', '$2y$10$wO3r2i52oJzP9E8oDqzU3O4Uj3F5/Xw2eBwO1T6Y/h6l2qR7C/tZq', 1),
(4, '1022334455', 'Miguel Perdomo', 'miguelp@gmail.com', 'Estudiante', '$2y$10$wO3r2i52oJzP9E8oDqzU3O4Uj3F5/Xw2eBwO1T6Y/h6l2qR7C/tZq', 0),
(5, '1077726157', 'Juliana Andrea Urazán Castañeda', 'julia@gmail.com', 'Estudiante', '123456', 1),
(6, '1077726863', 'Juan Manuel Barreiro Toledo', 'juanma@gmail.com', 'Estudiante', '123456', 1);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `prestamos`
--
ALTER TABLE `prestamos`
  ADD PRIMARY KEY (`id_prestamo`),
  ADD KEY `id_usuario` (`id_usuario`),
  ADD KEY `id_recurso` (`id_recurso`);

--
-- Indices de la tabla `recursos`
--
ALTER TABLE `recursos`
  ADD PRIMARY KEY (`id_recurso`),
  ADD UNIQUE KEY `codigo_inventario` (`codigo_inventario`);

--
-- Indices de la tabla `sanciones`
--
ALTER TABLE `sanciones`
  ADD PRIMARY KEY (`id_sancion`),
  ADD KEY `id_prestamo` (`id_prestamo`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `documento` (`documento`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `prestamos`
--
ALTER TABLE `prestamos`
  MODIFY `id_prestamo` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `recursos`
--
ALTER TABLE `recursos`
  MODIFY `id_recurso` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `sanciones`
--
ALTER TABLE `sanciones`
  MODIFY `id_sancion` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id_usuario` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `prestamos`
--
ALTER TABLE `prestamos`
  ADD CONSTRAINT `prestamos_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`),
  ADD CONSTRAINT `prestamos_ibfk_2` FOREIGN KEY (`id_recurso`) REFERENCES `recursos` (`id_recurso`);

--
-- Filtros para la tabla `sanciones`
--
ALTER TABLE `sanciones`
  ADD CONSTRAINT `sanciones_ibfk_1` FOREIGN KEY (`id_prestamo`) REFERENCES `prestamos` (`id_prestamo`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
