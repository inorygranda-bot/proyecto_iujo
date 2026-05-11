const fs = require('fs/promises');
const path = require('path');

class ProcesadorBiometrico {
  constructor(carpetaSalida) {
    this.registrosCrudos = [];
    this.listaAsistencia = [];
    this.listaErrores = [];
    this.listaAdvertencias = [];
    this.carpetaSalida = carpetaSalida || './reportes';
  }

  // PARTE 1: CARGA Y VALIDACION DE ARCHIVO
  async cargarArchivo(rutaArchivo) {
    try {
      this.limpiarDatos();
      
      await this.validarArchivo(rutaArchivo);
      
      const contenido = await fs.readFile(rutaArchivo, 'utf-8');
      const lineas = contenido.trim().split(/\r?\n/);

      this.procesarLineas(lineas);
      this.generarListaAsistencia();
      await this.crearReporte(rutaArchivo);
      
      console.log('PROCESAMIENTO COMPLETADO');
      console.log('Registros leidos: ' + this.registrosCrudos.length);
      console.log('Asistencias validas: ' + this.listaAsistencia.length);
      
      return this;
      
    } catch (error) {
      console.error('ERROR CRITICO: ' + error.message);
      throw new Error('Error en procesamiento: ' + error.message);
    }
  }

  async validarArchivo(rutaArchivo) {
    if (!rutaArchivo || typeof rutaArchivo !== 'string') {
      throw new Error('Ruta de archivo invalida');
    }

    try {
      await fs.access(rutaArchivo, fs.constants.R_OK);
    } catch {
      throw new Error('Archivo no encontrado: ' + rutaArchivo);
    }

    const extension = path.extname(rutaArchivo).toLowerCase();
    if (extension !== '.txt') {
      throw new Error('Solo se aceptan archivos TXT');
    }
  }

  // PARTE 2: PROCESAMIENTO DE LINEAS - NUEVO FORMATO 5 CAMPOS
  procesarLineas(lineas) {
    for (let i = 0; i < lineas.length; i++) {
      const linea = lineas[i].trim();
      if (!linea) continue;

      try {
        const partes = linea.split(/\s+/);
        if (partes.length !== 5) {
          this.agregarError('Linea ' + (i + 1) + ': Deben ser 5 campos exactamente', linea);
          continue;
        }

        // NUEVO ORDEN: ID_BIOMETRICO, ID_EMPLEADO, FECHA, HORA, DEPARTAMENTO
        const [idBiometrico, idEmpleado, fecha, hora, departamentoStr] = partes;
        
        this.validarCamposNuevos(idBiometrico, idEmpleado, fecha, hora, departamentoStr, i + 1);
        
        const registro = {
          idBiometrico: idBiometrico.trim(),
          idEmpleado: idEmpleado.trim(),
          fecha: fecha.trim(),
          hora: hora.trim(),
          departamento: parseInt(departamentoStr.trim(), 10),
          numeroLinea: i + 1
        };

        this.registrosCrudos.push(registro);

      } catch (err) {
        this.agregarError('Linea ' + (i + 1) + ': ' + err.message, linea);
      }
    }
  }

  // VALIDACION PARA NUEVO FORMATO CON DEPARTAMENTO (1-100)
  validarCamposNuevos(idBiometrico, idEmpleado, fecha, hora, departamentoStr, numeroLinea) {
    // ID biometrico: numero
    if (!/^\d+$/.test(idBiometrico)) {
      throw new Error('ID biometrico debe ser numero');
    }
    
    // ID empleado: numero
    if (!/^\d+$/.test(idEmpleado)) {
      throw new Error('ID empleado debe ser numero');
    }
    
    // Fecha: xx/xx/xx
    if (!/^\d{2}\/\d{2}\/\d{2}$/.test(fecha)) {
      throw new Error('Fecha debe ser xx/xx/xx');
    }
    
    // Hora: xx:xx
    if (!/^\d{2}:\d{2}$/.test(hora)) {
      throw new Error('Hora debe ser xx:xx');
    }
    
    // Departamento: 1-100
    const departamento = parseInt(departamentoStr, 10);
    if (isNaN(departamento)) {
      throw new Error('Departamento debe ser numero');
    }
    if (departamento < 1 || departamento > 100) {
      throw new Error('Departamento debe estar entre 1 y 100');
    }
  }

  // PARTE 3: LOGICA DE ASISTENCIA (INCLUYE DEPARTAMENTO)
  generarListaAsistencia() {
    const agrupados = new Map();

    for (const registro of this.registrosCrudos) {
      const clave = registro.idEmpleado + '-' + registro.fecha;
      if (!agrupados.has(clave)) {
        agrupados.set(clave, []);
      }
      agrupados.get(clave).push(registro);
    }

    for (const [clave, registros] of agrupados) {
      const [idEmpleado, fecha] = clave.split('-');
      
      // Tomar primer departamento encontrado (o el mas frecuente)
      const departamento = registros[0].departamento;
      
      const asistencia = this.analizarAsistenciaDiaria(registros, idEmpleado, fecha, departamento);
      this.listaAsistencia.push(asistencia);
    }
  }

  analizarAsistenciaDiaria(registros, idEmpleado, fecha, departamento) {
    // Ordenar por hora
    registros.sort((a, b) => this.horaAMinutos(a.hora) - this.horaAMinutos(b.hora));
    
    let entrada = null;
    let salida = null;
    const marcasExtras = [];
    const errores = [];

    if (registros.length === 0) {
      return { 
        idEmpleado, 
        fecha, 
        departamento,
        entrada, 
        salida, 
        estado: 'SIN_MARCAS', 
        errores: ['No hay registros'] 
      };
    }

    // Logica para multiples marcas
    if (registros.length === 1) {
      entrada = registros[0].hora;
      this.agregarAdvertencia('Empleado ' + idEmpleado + ' fecha ' + fecha + ': Solo entrada');
    } 
    else if (registros.length === 2) {
      entrada = registros[0].hora;
      salida = registros[1].hora;
    } 
    else {
      // Mas de 2: primera=entrada, ultima=salida
      entrada = registros[0].hora;
      salida = registros[registros.length - 1].hora;
      
      for (let i = 1; i < registros.length - 1; i++) {
        marcasExtras.push(registros[i].hora);
      }
      
      this.agregarAdvertencia('Empleado ' + idEmpleado + ': ' + registros.length + ' marcas el ' + fecha);
    }

    const estado = this.calcularEstado(entrada, salida);
    return { 
      idEmpleado, 
      fecha, 
      departamento,
      entrada, 
      salida, 
      marcasExtras, 
      estado, 
      errores 
    };
  }

  horaAMinutos(hora) {
    const [h, m] = hora.split(':').map(Number);
    return h * 60 + m;
  }

  calcularEstado(entrada, salida) {
    if (!entrada && !salida) return 'SIN_MARCAS';
    if (entrada && !salida) return 'SOLO_ENTRADA';
    if (!entrada && salida) return 'SOLO_SALIDA';
    return 'COMPLETA';
  }

  // PARTE 4: REPORTE TXT CON DEPARTAMENTO
  async crearReporte(rutaArchivoEntrada) {
    await fs.mkdir(this.carpetaSalida, { recursive: true });
    
    const fechaHora = new Date().toISOString().replace(/[:.]/g, '-');
    const archivoSalida = path.join(this.carpetaSalida, 'ASISTENCIA_' + fechaHora + '.txt');
    
    let contenido = 'REPORTE DE ASISTENCIA BIOMETRICA\n';
    contenido += 'Archivo origen: ' + path.basename(rutaArchivoEntrada) + '\n';
    contenido += 'Fecha: ' + new Date().toLocaleString('es-ES') + '\n\n';

    contenido += 'RESUMEN GENERAL:\n';
    contenido += '- Registros procesados: ' + this.registrosCrudos.length + '\n';
    contenido += '- Asistencias: ' + this.listaAsistencia.length + '\n';
    contenido += '- Completas: ' + this.listaAsistencia.filter(r => r.estado === 'COMPLETA').length + '\n';
    contenido += '- Solo entrada: ' + this.listaAsistencia.filter(r => r.estado === 'SOLO_ENTRADA').length + '\n\n';

    // NUEVA COLUMNA DEPARTAMENTO
    contenido += 'DETALLE DE ASISTENCIA:\n';
    contenido += 'EMP_ID\tFECHA\tDEPT\tENTRADA\tSALIDA\tESTADO\n';
    
    for (const registro of this.listaAsistencia) {
      contenido += registro.idEmpleado + '\t' + 
                   registro.fecha + '\t' + 
                   registro.departamento + '\t' + 
                   (registro.entrada || '') + '\t' + 
                   (registro.salida || '') + '\t' + 
                   registro.estado + '\n';
    }

    if (this.listaErrores.length > 0) {
      contenido += '\nERRORES (' + this.listaErrores.length + '):\n';
      this.listaErrores.forEach(error => contenido += '- ' + error + '\n');
    }

    if (this.listaAdvertencias.length > 0) {
      contenido += '\nADVERTENCIAS (' + this.listaAdvertencias.length + '):\n';
      this.listaAdvertencias.forEach(advertencia => contenido += '- ' + advertencia + '\n');
    }

    await fs.writeFile(archivoSalida, contenido, 'utf-8');
    console.log('Reporte generado: ' + archivoSalida);
  }

  // PARTE 5: METODOS PUBLICOS
  limpiarDatos() {
    this.registrosCrudos = [];
    this.listaAsistencia = [];
    this.listaErrores = [];
    this.listaAdvertencias = [];
  }

  agregarError(mensaje, linea) {
    const error = mensaje + (linea ? ' | Linea: "' + linea + '"' : '');
    this.listaErrores.push(error);
  }

  agregarAdvertencia(mensaje) {
    this.listaAdvertencias.push(mensaje);
  }

  obtenerResumen() {
    return {
      totalRegistros: this.registrosCrudos.length,
      asistenciasValidas: this.listaAsistencia.length,
      totalErrores: this.listaErrores.length,
      totalAdvertencias: this.listaAdvertencias.length
    };
  }

  obtenerListaAsistencia() {
    return [...this.listaAsistencia];
  }

  buscarEmpleado(idEmpleado) {
    return this.listaAsistencia.filter(r => r.idEmpleado === idEmpleado);
  }

  // Metodos requeridos por interfazVisual.html
  obtenerErrores() {
    return [...this.listaErrores];
  }

  obtenerAdvertencias() {
    return [...this.listaAdvertencias];
  }
}

module.exports = ProcesadorBiometrico;