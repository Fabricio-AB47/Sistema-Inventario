-- Esquema de base de datos para inventario de activos.
-- Basado en las tablas y relaciones solicitadas.

CREATE TABLE tipo_activo (
    id_tipo_activo INT IDENTITY NOT NULL,
    descripcion_tp_activo VARCHAR(150) NOT NULL,
    CONSTRAINT tipo_activo_pk PRIMARY KEY (id_tipo_activo)
);

CREATE TABLE tipo_mueble (
    id_tp_mueble INT IDENTITY NOT NULL,
    descripcion_tp_mueble VARCHAR(100) NOT NULL,
    CONSTRAINT tipo_mueble_pk PRIMARY KEY (id_tp_mueble)
);

CREATE TABLE muebles_enseres (
    id_mueble_enseres VARCHAR(150) NOT NULL,
    id_tp_mueble INT NOT NULL,
    fecha_adquision DATETIME NOT NULL,
    precio DECIMAL(10,2) NOT NULL,
    iva DECIMAL(10,2) NOT NULL,
    total_muebles_enseres DECIMAL(10,2) NOT NULL,
    tiempo_vida_util INT NOT NULL,
    num_factura VARCHAR(250) NOT NULL,
    id_tipo_activo INT NOT NULL,
    CONSTRAINT muebles_enseres_pk PRIMARY KEY (id_mueble_enseres)
);

CREATE TABLE dep_anual_m_e (
    id_dep_anual_m_e INT IDENTITY NOT NULL,
    id_mueble_enseres VARCHAR(150) NOT NULL,
    anio INT NOT NULL,
    total_dep_anual DECIMAL(10,2) NOT NULL,
    fecha_calculo DATETIME NOT NULL,
    CONSTRAINT dep_anual_m_e_pk PRIMARY KEY (id_dep_anual_m_e)
);

CREATE TABLE depreciacion_muebles_enseres (
    id_dep_m_e INT IDENTITY NOT NULL,
    anio INT NOT NULL,
    mes INT NOT NULL,
    fecha_dep DATETIME NOT NULL,
    valor_dep_mes DECIMAL(10,2) NOT NULL,
    id_mueble_enseres VARCHAR(150) NOT NULL,
    CONSTRAINT depreciacion_muebles_enseres_pk PRIMARY KEY (id_dep_m_e)
);

CREATE TABLE tipo_equipo (
    id_tp_equipo INT IDENTITY NOT NULL,
    descripcion_tp_equipo VARCHAR(100) NOT NULL,
    CONSTRAINT tipo_equipo_pk PRIMARY KEY (id_tp_equipo)
);

CREATE TABLE estado_asignacion_reasignacion (
    id_estado_asig_reasig INT IDENTITY NOT NULL,
    descripcion_est_asig_reasig VARCHAR(100) NOT NULL,
    CONSTRAINT estado_asignacion_reasignacion_pk PRIMARY KEY (id_estado_asig_reasig)
);

CREATE TABLE estado_equipo (
    id_estado_equipo INT IDENTITY NOT NULL,
    descripcion_estado_equipo VARCHAR(100) NOT NULL,
    CONSTRAINT estado_equipo_pk PRIMARY KEY (id_estado_equipo)
);

CREATE TABLE estado_activo (
    id_estado_activo INT IDENTITY NOT NULL,
    descripcion_estado_activo VARCHAR(100) NOT NULL,
    CONSTRAINT estado_activo_pk PRIMARY KEY (id_estado_activo)
);

CREATE TABLE equipo (
    id_equipo VARCHAR(150) NOT NULL,
    marca VARCHAR(75) NOT NULL,
    modelo VARCHAR(150) NOT NULL,
    num_serie VARCHAR(100) NOT NULL,
    memoria_ram INT NOT NULL,
    almacenamiento INT NOT NULL,
    hostname VARCHAR(150) NOT NULL,
    precio DECIMAL(10,2) NOT NULL,
    iva DECIMAL(10,2) NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    tiempo_vida_util INT NOT NULL,
    fecha_adquisicion DATETIME NOT NULL,
    num_factura VARCHAR(250) NOT NULL,
    id_estado_activo INT NOT NULL,
    id_estado_equipo INT NOT NULL,
    id_tp_equipo INT NOT NULL,
    id_tipo_activo INT NOT NULL,
    CONSTRAINT equipo_pk PRIMARY KEY (id_equipo)
);

CREATE TABLE dep_anual (
    id_dep_anual INT IDENTITY NOT NULL,
    id_equipo VARCHAR(150) NOT NULL,
    anio INT NOT NULL,
    total_dep_anual DECIMAL(10,2) NOT NULL,
    fecha_calculo DATETIME NOT NULL,
    CONSTRAINT dep_anual_pk PRIMARY KEY (id_dep_anual)
);

CREATE TABLE depreciacion_equipos_tec (
    id_dep_equi_tec INT IDENTITY NOT NULL,
    id_equipo VARCHAR(150) NOT NULL,
    anio INT NOT NULL,
    mes INT NOT NULL,
    fecha_dep DATETIME NOT NULL,
    valor_dep_mes DECIMAL(10,2) NOT NULL,
    CONSTRAINT depreciacion_equipos_tec_pk PRIMARY KEY (id_dep_equi_tec)
);

CREATE TABLE tipo_perfil (
    id_tp_perfil INT IDENTITY NOT NULL,
    descripcion_perfil VARCHAR(100) NOT NULL,
    CONSTRAINT tipo_perfill_pk PRIMARY KEY (id_tp_perfil)
);

CREATE TABLE usuario (
    id_user INT IDENTITY NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    apellidos VARCHAR(150) NOT NULL,
    cedula VARCHAR(15) NOT NULL,
    correo VARCHAR(250) NOT NULL,
    pwd VARCHAR(500) NOT NULL,
    id_tp_perfil INT NOT NULL,
    CONSTRAINT usuario_pk PRIMARY KEY (id_user)
);

CREATE TABLE asignacion_reasignacion_m_e (
    id_asig_reasig INT IDENTITY NOT NULL,
    fecha_asig_reasig DATETIME NOT NULL,
    id_mueble_enseres VARCHAR(150) NOT NULL,
    id_user INT NOT NULL,
    id_estado_asig_reasig INT NOT NULL,
    CONSTRAINT asignacion_reasignacion_m_e_pk PRIMARY KEY (id_asig_reasig)
);

CREATE TABLE auditoria_movimientos (
    id_auditoria INT IDENTITY NOT NULL,
    tabla_afectada VARCHAR(500) NOT NULL,
    id_registro_afectado VARCHAR(250) NOT NULL,
    accion VARCHAR(500) NOT NULL,
    fecha DATETIME NOT NULL,
    detalle VARCHAR(500) NOT NULL,
    id_user INT NOT NULL,
    CONSTRAINT auditoria_movimientos_pk PRIMARY KEY (id_auditoria)
);

CREATE TABLE asignacion_reasignacion (
    id_asig_reasig INT IDENTITY NOT NULL,
    id_user INT NOT NULL,
    id_equipo VARCHAR(150) NOT NULL,
    fecha_asig_reasig DATETIME NOT NULL,
    id_estado_asig_reasig INT NOT NULL,
    CONSTRAINT asignacion_reasignacion_pk PRIMARY KEY (id_asig_reasig)
);

ALTER TABLE equipo ADD CONSTRAINT tipo_activo_equipo_fk
FOREIGN KEY (id_tipo_activo)
REFERENCES tipo_activo (id_tipo_activo)
ON DELETE NO ACTION
ON UPDATE NO ACTION;

ALTER TABLE muebles_enseres ADD CONSTRAINT tipo_activo_muebles_enseres_fk
FOREIGN KEY (id_tipo_activo)
REFERENCES tipo_activo (id_tipo_activo)
ON DELETE NO ACTION
ON UPDATE NO ACTION;

ALTER TABLE muebles_enseres ADD CONSTRAINT tipo_mueble_muebles_enseres_fk
FOREIGN KEY (id_tp_mueble)
REFERENCES tipo_mueble (id_tp_mueble)
ON DELETE NO ACTION
ON UPDATE NO ACTION;

ALTER TABLE depreciacion_muebles_enseres ADD CONSTRAINT muebles_enseres_depreciacion_muebles_enseres_fk
FOREIGN KEY (id_mueble_enseres)
REFERENCES muebles_enseres (id_mueble_enseres)
ON DELETE NO ACTION
ON UPDATE NO ACTION;

ALTER TABLE dep_anual_m_e ADD CONSTRAINT muebles_enseres_dep_anual_m_e_fk
FOREIGN KEY (id_mueble_enseres)
REFERENCES muebles_enseres (id_mueble_enseres)
ON DELETE NO ACTION
ON UPDATE NO ACTION;

ALTER TABLE asignacion_reasignacion_m_e ADD CONSTRAINT muebles_enseres_asignacion_reasignacion_1_fk
FOREIGN KEY (id_mueble_enseres)
REFERENCES muebles_enseres (id_mueble_enseres)
ON DELETE NO ACTION
ON UPDATE NO ACTION;

ALTER TABLE equipo ADD CONSTRAINT tipo_equipo_equipo_fk
FOREIGN KEY (id_tp_equipo)
REFERENCES tipo_equipo (id_tp_equipo)
ON DELETE NO ACTION
ON UPDATE NO ACTION;

ALTER TABLE asignacion_reasignacion ADD CONSTRAINT estado_asignacion_reasignacion_asignacion_reasignacion_fk
FOREIGN KEY (id_estado_asig_reasig)
REFERENCES estado_asignacion_reasignacion (id_estado_asig_reasig)
ON DELETE NO ACTION
ON UPDATE NO ACTION;

ALTER TABLE asignacion_reasignacion_m_e ADD CONSTRAINT estado_asignacion_reasignacion_asignacion_reasignacion_m_e_fk
FOREIGN KEY (id_estado_asig_reasig)
REFERENCES estado_asignacion_reasignacion (id_estado_asig_reasig)
ON DELETE NO ACTION
ON UPDATE NO ACTION;

ALTER TABLE equipo ADD CONSTRAINT estado_equipo_equipo_fk
FOREIGN KEY (id_estado_equipo)
REFERENCES estado_equipo (id_estado_equipo)
ON DELETE NO ACTION
ON UPDATE NO ACTION;

ALTER TABLE equipo ADD CONSTRAINT estado_activo_equipo_fk
FOREIGN KEY (id_estado_activo)
REFERENCES estado_activo (id_estado_activo)
ON DELETE NO ACTION
ON UPDATE NO ACTION;

ALTER TABLE depreciacion_equipos_tec ADD CONSTRAINT equipo_depreciacion_equipos_tec_fk
FOREIGN KEY (id_equipo)
REFERENCES equipo (id_equipo)
ON DELETE NO ACTION
ON UPDATE NO ACTION;

ALTER TABLE asignacion_reasignacion ADD CONSTRAINT equipo_asignacion_reasignacion_fk
FOREIGN KEY (id_equipo)
REFERENCES equipo (id_equipo)
ON DELETE NO ACTION
ON UPDATE NO ACTION;

ALTER TABLE dep_anual ADD CONSTRAINT equipo_dep_anual_fk
FOREIGN KEY (id_equipo)
REFERENCES equipo (id_equipo)
ON DELETE NO ACTION
ON UPDATE NO ACTION;

ALTER TABLE usuario ADD CONSTRAINT tipo_perfil_usuario_fk
FOREIGN KEY (id_tp_perfil)
REFERENCES tipo_perfil (id_tp_perfil)
ON DELETE NO ACTION
ON UPDATE NO ACTION;

ALTER TABLE asignacion_reasignacion ADD CONSTRAINT usuario_asignacion_reasignacion_fk
FOREIGN KEY (id_user)
REFERENCES usuario (id_user)
ON DELETE NO ACTION
ON UPDATE NO ACTION;

ALTER TABLE auditoria_movimientos ADD CONSTRAINT usuario_auditoria_movimientos_fk
FOREIGN KEY (id_user)
REFERENCES usuario (id_user)
ON DELETE NO ACTION
ON UPDATE NO ACTION;

ALTER TABLE asignacion_reasignacion_m_e ADD CONSTRAINT usuario_asignacion_reasignacion_1_fk
FOREIGN KEY (id_user)
REFERENCES usuario (id_user)
ON DELETE NO ACTION
ON UPDATE NO ACTION;
