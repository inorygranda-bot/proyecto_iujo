const ProcesadorBiometrico = require('./procesadorBiometrico.js');

async function programaPrincipal() {
  console.log('INICIANDO PROCESADOR BIOMETRICO');
  
  const procesador = new ProcesadorBiometrico('./reportes');
  
  try {
    // CAMBIA ESTA RUTA POR TU ARCHIVO TXT
    await procesador.cargarArchivo('./OMEGA.TXT');
    
    // EJEMPLO: Buscar empleado
    const empleado123 = procesador.buscarEmpleado('123');
    console.log('Empleado 123:', empleado123);
    
    // Mostrar resumen
    const resumen = procesador.obtenerResumen();
    console.log('RESUMEN:', resumen);
    
  } catch (error) {
    console.error('ERROR DEL PROGRAMA:', error.message);
  }
}

// EJECUTAR PROGRAMA
programaPrincipal();