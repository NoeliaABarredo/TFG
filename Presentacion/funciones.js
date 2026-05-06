// Constantes generales de la presentacion
const EMAIL_PATTERN = /^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,6}$/;
const SESSION_OUT = ["Session out"];
const MENSAJES_ERROR = [
	"Error de login",
	"Error al registrar usuario",
	"Error al leer los datos del usuario",
	"Error al modificar usuario",
	"Sesion invalida",
	"Error al leer cuentas",
	"Error al modificar cuenta",
	"Error al añadir ingreso",
	"Error al añadir gasto",
	"Fallo al conectar con el servidor SQL de cuentas",
	"Fallo al conectar con la base de datos cuentas_db",
	"Saldo insuficiente"
];
const ERROR_CONEXION_BASEDATOS = [
	"Fallo al conectar con el servidor SQL de cuentas",
	"Fallo al conectar con la base de datos cuentas_db",
	"Fallo al conectar con usuarios_db",
	"Fallo al conectar con operaciones_db"
];
// Variables globales
datosUsuario = {};
listaCuentasGlobal = {};

//#region eventosDOM
document.addEventListener('DOMContentLoaded', () => {
	//Comprobación de la sesión
	existeSesion();

	//Conectar usuario
	const btnConectar = document.getElementById('btnConectar');
	if(btnConectar){btnConectar.addEventListener('click', function(event){	
		let idlogin = document.querySelector("#idlogin").value;
		if (EMAIL_PATTERN.test(idlogin))
			sendForm('/api/usuarios.php/login','formLogin','operacionesTrasLogin','POST');
		else
			document.querySelector("#log_error").style.display = "block";
		console.log("Conectar");
		});
	}	
	const btnRegistrar = document.getElementById('btnRegistrar');
	if(btnRegistrar){btnRegistrar.addEventListener('click', function(event){	
		mostrarFormularioRegistro();
		console.log("Registrar");
		});
	}
	
	//Registrar usuario
	const btnRegistrarUsuario = document.getElementById('btnRegistrarUsuario');
	if(btnRegistrarUsuario){btnRegistrarUsuario.addEventListener('click', function(event){	
		sendForm('/api/usuarios.php/registro','formRegistro','operacionesTrasRegistro','POST');
		console.log("Registrar");
		});
	}

	// Volver de registrar usuario
	const btnVolverAtras = document.getElementById('btnVolverAtras');
	if(btnVolverAtras){btnVolverAtras.addEventListener('click', function(event){	
		window.location="./index.html";
		console.log("Volver");
		});
	}
	
	//Salir
		const btnVolverServicios = document.getElementById('btnVolverServicios');
	if(btnVolverServicios){btnVolverServicios.addEventListener('click', function(event){	
		window.location="./menuServicios.html";
		console.log("Volver atrás");
		});
	}

	const btnSalirApp = document.getElementById('btnSalirApp');
	if(btnSalirApp){btnSalirApp.addEventListener('click', function(event){
		localStorage.removeItem('jwt_token');	
		window.location="./index.html";
		console.log("Salir de la app");
		});
	}

	// Menú para seleccionar servicios
    const seleccionarServicio = document.getElementById('seleccionarServicio');
    if (seleccionarServicio) {
        seleccionarServicio.addEventListener('click', (e) => {
            // Verificamos que se pulsó un botón
            if (e.target.tagName === 'INPUT' && e.target.type === 'button') {
                const id = e.target.id;
    
                if (id === 'btnMostrarCuentas') {
                    console.log("Mostrando servicio de cuentas...");
                    mostrarServicioCuentas();
					pedirListaCuentas();
                } else if (id === 'btnMostrarOperaciones') {
                    console.log("Mostrando operaciones...");
					mostrarServicioOperaciones();
					// Se recuperan las operaciones de todas las cuentas del usuario
					solicitarOperaciones();
                } else if (id === 'btnMostrarAnalisis') {
                    console.log("Mostrando análisis...");
					mostrarServicioAnalisis();
					solicitarAnalisis();
					// Se añaden eventos a los botones 
					let btnAnalisisAhorro = document.querySelector("#btnAnalisisAhorro");
					btnAnalisisAhorro.addEventListener("click", calcularCapacidadAhorro);
					
                } else if (id == 'btnMostrarUsuario'){					
					console.log("Ver datos de usuario");
					mostarFormularioModificarUsuario();
					const datos = {
						'token' : localStorage.getItem('jwt_token')
					}
					sendData("/api/usuarios.php/leerUsuario",datos,"accionesUsuarioLeido","POST");				
				}		
       		}
   		});
    }

    // Cuentas
    const formCuentas = document.getElementById('formServicioCuentas');
    if (formCuentas) {
        formCuentas.addEventListener('click', (e) => {
            if (e.target.tagName === 'INPUT' && e.target.type === 'button') {
                const id = e.target.id;

                switch (id) {
                    case 'btnCrearCuenta':
						document.querySelector("#crearCuentaToken").value = localStorage.getItem("jwt_token");
						sendForm('/api/cuentas.php/crear-cuenta','formServicioCuentas','TrasCrearCuenta','POST');
                        break;
					case 'btnOperarCuentaSeleccionada':
						console.log("operar con cuenta seleccionada");
						mostrarMenuOperarCuenta();
						break;

					case 'btnOperarConCuentaIngresar':
						console.log("Introducir ingreso");
						mostrarOperarConCuentaIngresar();
						// Se añade el evento al botón confirmar
						let botonConfirmarIngreso = document.querySelector("#btnConfirmarIngreso");
						botonConfirmarIngreso.addEventListener("click", enviarIngreso);
						break;

					case 'btnOperarConCuentaGastar':
						console.log("Introducir gasto");
						mostrarOperarConCuentaGastar();
						// Se añade el evento al botón confirmar
						let botonConfirmarGasto = document.querySelector("#btnConfirmarGasto");
						botonConfirmarGasto.addEventListener("click", enviarGasto);
						break;

					case 'btnOperarConCuentaTransferir':
						console.log("Ordenar transferencia");
						mostrarOperarConCuentaTransferir();
						prepararFormularioTrasferencias();
						// Se añade el evento al botón confirmar
						let botonConfirmarTransferir = document.querySelector("#btnConfirmarTransferir");
						botonConfirmarTransferir.addEventListener("click", enviarTransferencia);
						break;

					case 'btnVolverMenuParaOperarConCuenta':
						console.log("Volver");
						mostrarMenuOperarCuenta();
						break;						

					case 'btnVolverDesdeOperar':
						mostrarServicioCuentas();
						pedirListaCuentas();
						break;

					case 'btnModificarCuentaSeleccionada':
						console.log("modificar cuenta");
						mostrarMenuModificarCuenta();
						// Se traen los elementos implicados y se 
						// cumplimenta el formulario con los datos actuales
						let nuevoNombreCuenta = document.querySelector("#nuevoNombreCuenta");
						let nombreCuenta = document.querySelector("#cuentaActual span.nombreCuenta");
						let nuevoSaldoCuenta = document.querySelector("#nuevoSaldoCuenta");
						let saldoCuenta = document.querySelector("#cuentaActual p.saldo");
						//let botonModificar = document.querySelector("#btnModificarCuenta");
						nuevoNombreCuenta.value = nombreCuenta.textContent;
						nuevoSaldoCuenta.value = saldoCuenta.textContent;
						break;

					case 'btnEliminarCuentaSeleccionada':
						console.log("eliminar cuenta");
						mostrarMenuEliminarCuenta();
						// Completo los campos para mostrar los datos de la cuenta que se va a borrar
						let eliminarNombreCuenta = document.querySelector("#menuEliminarCuenta p.nombreCuenta");
						let eliminarSaldoCuenta = document.querySelector("#menuEliminarCuenta p.saldoCuenta");
						eliminarNombreCuenta.textContent = document.querySelector("#cuentaActual span.nombreCuenta").textContent;
						eliminarSaldoCuenta.textContent = document.querySelector("#cuentaActual p.saldo").textContent;
						// Añado el evento para el botón de eliminar
						let botonEliminarCuenta = document.querySelector("#btnEliminarCuenta");
						botonEliminarCuenta.addEventListener("click", () => {
							document.querySelector("#crearCuentaToken").value = localStorage.getItem('jwt_token');
							sendForm('/api/cuentas.php/borrar-cuenta','formServicioCuentas','TrasBorrarCuenta','POST');
						});
						break;
                    case 'btnModificarCuenta':
						solicitarModificacion();
						console.log("Modificar"); 
                        break;
					case 'btnCancelarModificar':
						mostrarServicioCuentas();
						pedirListaCuentas();
                        console.log("Cancelar");
                        break;
					case 'btnCancelarEliminar':
						mostrarServicioCuentas();
						pedirListaCuentas();
                        console.log("Cancelar");
                        break;
					case 'btnCancelarIngreso':
						mostrarServicioCuentas();
						pedirListaCuentas();
                        console.log("Cancelar");
						break
					case 'btnCancelarGasto':
						mostrarServicioCuentas();
						pedirListaCuentas();
                        console.log("Cancelar");
						break
                }
            }
        });
    }

    // Lista de cuentas
    const listaCuentas = document.querySelector('.lista');
    if (listaCuentas) {
        listaCuentas.addEventListener('click', (e) => {
            if (e.target.classList.contains('cuenta-item')) {
                e.preventDefault(); // Evita que el enlace recargue la página
                const nombreCuenta = e.target.textContent;
                console.log("Has seleccionado: " + nombreCuenta);
                // Lógica para cargar los datos de esa cuenta específica
				mostrarMenuCuentaSeleccionada()
            }
        });
    }

	//Formatear Euros
   	aplicarFormatoEuro("saldoNuevaCuenta");
   	aplicarFormatoEuro("nuevoSaldoCuenta");
	aplicarFormatoEuro("montoIngresar");
	aplicarFormatoEuro("montoGastar");
	aplicarFormatoEuro("montoTransferir");

	    // Mis Operaciones
    const misOperaciones = document.getElementById('servicioOperaciones');
    if (misOperaciones) {
        misOperaciones.addEventListener('click', (e) => {
            if (e.target.tagName === 'INPUT' && e.target.type === 'button') {
                const id = e.target.id;
                switch (id) {
                    case 'btnFiltrarOperaciones':
                        //Hacer
						console.log("Filtrar operaciones");
                        break;
                }
            }
        });
    }

	//Modificar usuario
	const misDatosUsuario = document.getElementById('apartadoModificarDatosUsuario');
	if (misDatosUsuario) {
		misDatosUsuario.addEventListener('click', (e) => {
			if (e.target.tagName === 'INPUT' && e.target.type === 'button') {
                const id = e.target.id;
				if ( id === 'btnConfirmarModificarUsuario'){
					console.log("Modificar usuario");
					sendForm('/api/usuarios.php/modificarUsuario','formularioModificarDatosUsuario','operacionesTrasModificar','POST');

				} else if (id === 'btnResetModificarUsuario'){
					console.log("Restaurar usuario");
					const datos = {
						'token' : localStorage.getItem('jwt_token')
					}
					sendData("/api/usuarios.php/leerUsuario",datos,"accionesUsuarioLeido","POST");
				}
			}
		});
	}
}); 
//#endregion

//Formulario registrar usuario
function mostrarFormularioRegistro(){
	// Ocultamos un posible mensaje de error en login previo
	document.querySelector("#log_error").style.display = "none";

	let capalogin = document.getElementById("capalogin");
	let camposFormRegistro = document.getElementById("camposFormRegistro");
	let botonRegistrarUsuario = document.getElementById("botonRegistrarUsuario");
	let botonModificarUsuario = document.getElementById("botonModificarUsuario");
	
	if (capalogin) capalogin.style.display = "none";
	if (camposFormRegistro) camposFormRegistro.style.display = "block";
	if (botonRegistrarUsuario) botonRegistrarUsuario.style.display = "block";
	if (botonModificarUsuario) botonModificarUsuario.style.display = "none";
	console.log("entra");
}

//#region Mostrar/ocultar servicios
function mostrarMenuServicios(){
	window.location = "./menuServicios.html";
}

function ocultarMisCuentas(){
	console.log("No ver mis cuentas");

	let servicioCuentas = document.getElementById("servicioCuentas");
	let seleccionarServicio = document.getElementById("seleccionarServicio");
	if (servicioCuentas) servicioCuentas.style.display = "none";
	if (seleccionarServicio) seleccionarServicio.style.display = "none";
}

function ocultarMisOperaciones(){
	console.log("No ver mis ops");

	let servicioOperaciones = document.getElementById("servicioOperaciones");
	let seleccionarServicio = document.getElementById("seleccionarServicio");
	if (servicioOperaciones) servicioOperaciones.style.display = "none";
	if (seleccionarServicio) seleccionarServicio.style.display = "none";
}

function ocultarMisFinanzas(){
	console.log("No ver mis finzanzas");

	let servicioAnalisis = document.getElementById("servicioAnalisis");
	let seleccionarServicio = document.getElementById("seleccionarServicio");
	if (servicioAnalisis) servicioAnalisis.style.display = "none";
	if (seleccionarServicio) seleccionarServicio.style.display = "none";
}

function ocultarModificarDatos(){
	let apartadoModificarDatosUsuario = document.getElementById("apartadoModificarDatosUsuario");
	let seleccionarServicio = document.getElementById("seleccionarServicio");
	if (apartadoModificarDatosUsuario) apartadoModificarDatosUsuario.style.display = "none";
	if (seleccionarServicio) seleccionarServicio.style.display = "none";
}

function ocultarMenusCuentas(){
	let servicioCuentas = document.getElementById("servicioCuentas");
	let menuSeleccionarCuenta = document.getElementById("menuSeleccionarCuenta");
	let menuCrearCuenta = document.getElementById("menuCrearCuenta");
	let menuTrabajarConCuenta = document.getElementById("menuTrabajarConCuenta");
	let menuModificarCuenta = document.getElementById("menuModificarCuenta");
	let menuEliminarCuenta = document.getElementById("menuEliminarCuenta");	
	if (servicioCuentas) servicioCuentas.style.display = "block";
	if (menuSeleccionarCuenta) menuSeleccionarCuenta.style.display = "none";
	if (menuCrearCuenta) menuCrearCuenta.style.display = "none";
	if (menuTrabajarConCuenta) menuTrabajarConCuenta.style.display = "none";
	if (menuModificarCuenta) menuModificarCuenta.style.display = "none";
	if (menuEliminarCuenta) menuEliminarCuenta.style.display = "none";
}

function mostrarMenuOperarCuenta(){
	ocultarMisOperaciones();
	ocultarMisFinanzas();
	ocultarModificarDatos();
	ocultarMenusCuentas();
	
	let menuBotonAtrasDesdeCualquierServicio = document.getElementById("menuBotonAtrasDesdeCualquierServicio");
	let menuTrabajarConCuenta = document.getElementById("menuTrabajarConCuenta");
	let botonesTrabajarConCuenta = document.getElementById("botonesTrabajarConCuenta");
	let menuParaOperarConCuenta = document.getElementById("menuParaOperarConCuenta");	
	let botonesMenuParaOperarConCuenta = document.getElementById("botonesMenuParaOperarConCuenta");
	let botonVolverMenuParaOperarConCuenta = document.getElementById("botonVolverMenuParaOperarConCuenta");
	let botonVolverDesdeOperar = document.getElementById("botonVolverDesdeOperar");	
	let operarConCuentaIngresar = document.getElementById("operarConCuentaIngresar");	
	let operarConCuentaGastar = document.getElementById("operarConCuentaGastar");	
	let operarConCuentaTransferir = document.getElementById("operarConCuentaTransferir");
	if (menuBotonAtrasDesdeCualquierServicio) menuBotonAtrasDesdeCualquierServicio.style.display = "block";
	if (menuTrabajarConCuenta) menuTrabajarConCuenta.style.display = "block";
	if (botonesTrabajarConCuenta) botonesTrabajarConCuenta.style.display = "none";
	if (menuParaOperarConCuenta) menuParaOperarConCuenta.style.display = "block";
	if (botonesMenuParaOperarConCuenta) botonesMenuParaOperarConCuenta.style.display = "block";
	if (botonVolverMenuParaOperarConCuenta) botonVolverMenuParaOperarConCuenta.style.display = "none";
	if (botonVolverDesdeOperar) botonVolverDesdeOperar.style.display = "block";
	if (operarConCuentaIngresar) operarConCuentaIngresar.style.display = "none";
	if (operarConCuentaGastar) operarConCuentaGastar.style.display = "none";
	if (operarConCuentaTransferir) operarConCuentaTransferir.style.display = "none";
}

function mostrarOperarConCuentaIngresar(){
	mostrarMenuOperarCuenta();
	
	let botonesMenuParaOperarConCuenta = document.getElementById("botonesMenuParaOperarConCuenta");
	let botonVolverMenuParaOperarConCuenta = document.getElementById("botonVolverMenuParaOperarConCuenta");
	let botonVolverDesdeOperar = document.getElementById("botonVolverDesdeOperar");
	let operarConCuentaIngresar = document.getElementById("operarConCuentaIngresar");	
	let operarConCuentaGastar = document.getElementById("operarConCuentaGastar");	
	let operarConCuentaTransferir = document.getElementById("operarConCuentaTransferir");	
	if (botonesMenuParaOperarConCuenta) botonesMenuParaOperarConCuenta.style.display = "none";
	if (botonVolverMenuParaOperarConCuenta) botonVolverMenuParaOperarConCuenta.style.display = "block";
	if (botonVolverDesdeOperar) botonVolverDesdeOperar.style.display = "none";
	if (operarConCuentaIngresar) operarConCuentaIngresar.style.display = "block";
	if (operarConCuentaGastar) operarConCuentaGastar.style.display = "none";
	if (operarConCuentaTransferir) operarConCuentaTransferir.style.display = "none";
}

function mostrarOperarConCuentaGastar(){
	mostrarMenuOperarCuenta();

	let botonesMenuParaOperarConCuenta = document.getElementById("botonesMenuParaOperarConCuenta");
	let botonVolverMenuParaOperarConCuenta = document.getElementById("botonVolverMenuParaOperarConCuenta");
	let botonVolverDesdeOperar = document.getElementById("botonVolverDesdeOperar");
	let operarConCuentaIngresar = document.getElementById("operarConCuentaIngresar");	
	let operarConCuentaGastar = document.getElementById("operarConCuentaGastar");	
	let operarConCuentaTransferir = document.getElementById("operarConCuentaTransferir");	
	if (botonesMenuParaOperarConCuenta) botonesMenuParaOperarConCuenta.style.display = "none";
	if (botonVolverMenuParaOperarConCuenta) botonVolverMenuParaOperarConCuenta.style.display = "block";
	if (botonVolverDesdeOperar) botonVolverDesdeOperar.style.display = "none";
	if (operarConCuentaIngresar) operarConCuentaIngresar.style.display = "none";
	if (operarConCuentaGastar) operarConCuentaGastar.style.display = "block";
	if (operarConCuentaTransferir) operarConCuentaTransferir.style.display = "none";
}

function mostrarOperarConCuentaTransferir(){
	mostrarMenuOperarCuenta();
	
	let botonesMenuParaOperarConCuenta = document.getElementById("botonesMenuParaOperarConCuenta");
	let botonVolverMenuParaOperarConCuenta = document.getElementById("botonVolverMenuParaOperarConCuenta");
	let botonVolverDesdeOperar = document.getElementById("botonVolverDesdeOperar");
	let operarConCuentaIngresar = document.getElementById("operarConCuentaIngresar");	
	let operarConCuentaGastar = document.getElementById("operarConCuentaGastar");	
	let operarConCuentaTransferir = document.getElementById("operarConCuentaTransferir");		
	if (botonesMenuParaOperarConCuenta) botonesMenuParaOperarConCuenta.style.display = "none";
	if (botonVolverMenuParaOperarConCuenta) botonVolverMenuParaOperarConCuenta.style.display = "block";
	if (botonVolverDesdeOperar) botonVolverDesdeOperar.style.display = "none";
	if (operarConCuentaIngresar) operarConCuentaIngresar.style.display = "none";
	if (operarConCuentaGastar) operarConCuentaGastar.style.display = "none";
	if (operarConCuentaTransferir) operarConCuentaTransferir.style.display = "block";
}

function mostrarServicioCuentas() {
	ocultarMisOperaciones();
	ocultarMisFinanzas();
	ocultarMenusCuentas();
	ocultarModificarDatos();

	let menuSeleccionarCuenta = document.getElementById("menuSeleccionarCuenta");
	let menuCrearCuenta = document.getElementById("menuCrearCuenta");
	let menuBotonAtrasDesdeCualquierServicio = document.getElementById("menuBotonAtrasDesdeCualquierServicio");
	let botonesTrabajarConCuenta = document.getElementById("botonesTrabajarConCuenta");
	if (menuSeleccionarCuenta) menuSeleccionarCuenta.style.display = "block";
	if (menuCrearCuenta) menuCrearCuenta.style.display = "block";
	if (menuBotonAtrasDesdeCualquierServicio) menuBotonAtrasDesdeCualquierServicio.style.display = "block";
	if (botonesTrabajarConCuenta) botonesTrabajarConCuenta.style.display = "none";
}

	
function seleccionarCuenta() {
	ocultarMenusCuentas();

	let menuTrabajarConCuenta = document.getElementById("menuTrabajarConCuenta");
	let botonesTrabajarConCuenta = document.getElementById("botonesTrabajarConCuenta");
	let menuBotonAtrasDesdeCualquierServicio = document.getElementById("menuBotonAtrasDesdeCualquierServicio");
	let menuParaOperarConCuenta = document.getElementById("menuParaOperarConCuenta");	
	if (menuTrabajarConCuenta) menuTrabajarConCuenta.style.display = "block";
	if (botonesTrabajarConCuenta) botonesTrabajarConCuenta.style.display = "block";
	if (menuBotonAtrasDesdeCualquierServicio) menuBotonAtrasDesdeCualquierServicio.style.display = "block";
	if (menuParaOperarConCuenta) menuParaOperarConCuenta.style.display = "none";	
}

function mostrarMenuModificarCuenta() {	
	ocultarMenusCuentas();

	let menuTrabajarConCuenta = document.getElementById("menuTrabajarConCuenta");
	let botonesTrabajarConCuenta = document.getElementById("botonesTrabajarConCuenta");
	let menuBotonAtrasDesdeCualquierServicio = document.getElementById("menuBotonAtrasDesdeCualquierServicio");
	let datosCuenta = document.getElementById("datosCuenta");
	let menuModificarCuenta = document.getElementById("menuModificarCuenta");
	if (menuTrabajarConCuenta) menuTrabajarConCuenta.style.display = "block";
	if (botonesTrabajarConCuenta) botonesTrabajarConCuenta.style.display = "none";
	if (menuBotonAtrasDesdeCualquierServicio) menuBotonAtrasDesdeCualquierServicio.style.display = "none";
	if (datosCuenta) datosCuenta.style.display = "block";
	if (menuModificarCuenta) menuModificarCuenta.style.display = "block";
}

function mostrarMenuEliminarCuenta() {
		ocultarMenusCuentas();

	let menuTrabajarConCuenta = document.getElementById("menuTrabajarConCuenta");
	let botonesTrabajarConCuenta = document.getElementById("botonesTrabajarConCuenta");
	let menuBotonAtrasDesdeCualquierServicio = document.getElementById("menuBotonAtrasDesdeCualquierServicio");
	let datosCuenta = document.getElementById("datosCuenta");
	let menuEliminarCuenta = document.getElementById("menuEliminarCuenta");
	if (menuTrabajarConCuenta) menuTrabajarConCuenta.style.display = "block";
	if (botonesTrabajarConCuenta) botonesTrabajarConCuenta.style.display = "none";
	if (menuBotonAtrasDesdeCualquierServicio) menuBotonAtrasDesdeCualquierServicio.style.display = "none";
	if (datosCuenta) datosCuenta.style.display = "block";
	if (menuEliminarCuenta) menuEliminarCuenta.style.display = "block";
}

function mostrarMenuCuentaSeleccionada(){
	ocultarMenusCuentas();

	let menuTrabajarConCuenta = document.getElementById("menuTrabajarConCuenta");
	let botonesTrabajarConCuenta = document.getElementById("botonesTrabajarConCuenta");
	let menuBotonAtrasDesdeCualquierServicio = document.getElementById("menuBotonAtrasDesdeCualquierServicio");
	let menuParaOperarConCuenta = document.getElementById("menuParaOperarConCuenta");
	if (menuTrabajarConCuenta) menuTrabajarConCuenta.style.display = "block";
	if (botonesTrabajarConCuenta) botonesTrabajarConCuenta.style.display = "block";
	if (menuBotonAtrasDesdeCualquierServicio) menuBotonAtrasDesdeCualquierServicio.style.display = "block";
	if (menuParaOperarConCuenta) menuParaOperarConCuenta.style.display = "none";
}

function mostrarServicioOperaciones() {
	ocultarMisCuentas();
	ocultarMisFinanzas();
	ocultarMenusCuentas();

	let servicioOperaciones = document.getElementById("servicioOperaciones");
	let menuBotonAtrasDesdeCualquierServicio = document.getElementById("menuBotonAtrasDesdeCualquierServicio");
	let seleccionarServicio = document.getElementById("seleccionarServicio");
	if (servicioOperaciones) servicioOperaciones.style.display = "block";
	if (menuBotonAtrasDesdeCualquierServicio) menuBotonAtrasDesdeCualquierServicio.style.display = "block";
	if (seleccionarServicio) seleccionarServicio.style.display = "none";
}

function mostrarServicioAnalisis(){
	console.log("Analisis");
	ocultarMisOperaciones();
	ocultarMisCuentas();
	ocultarMenusCuentas();
	ocultarModificarDatos();
	
	let servicioAnalisis = document.getElementById("servicioAnalisis");
	let menuBotonAtrasDesdeCualquierServicio = document.getElementById("menuBotonAtrasDesdeCualquierServicio");
	if (servicioAnalisis) servicioAnalisis.style.display = "block";
	if (menuBotonAtrasDesdeCualquierServicio) menuBotonAtrasDesdeCualquierServicio.style.display = "block";
}

function mostarFormularioModificarUsuario(){
	console.log("Modificar usuario");
	ocultarMisOperaciones();
	ocultarMisCuentas();
	ocultarMisFinanzas();
	ocultarMenusCuentas();

	let apartadoModificarDatosUsuario = document.getElementById("apartadoModificarDatosUsuario");
	let menuBotonAtrasDesdeCualquierServicio = document.getElementById("menuBotonAtrasDesdeCualquierServicio");
	if (apartadoModificarDatosUsuario) apartadoModificarDatosUsuario.style.display = "block";
	if (menuBotonAtrasDesdeCualquierServicio) menuBotonAtrasDesdeCualquierServicio.style.display = "block";
} 
//#endregion

//Sesion
function existeSesion(){
	if (localStorage.getItem('jwt_token') !== null){
		// Hay un token pero hay que comprobarlo
		const datos = {
			'token' : localStorage.getItem('jwt_token')
		}
		sendData("/api/usuarios.php/existeSesion",datos,"accionesTrasTestToken","POST");
	} else {
		// No hay token asique no hay sesion
		let direccionActual = window.location.href.split('/'.toLowerCase()).slice(-1)[0];
		if (direccionActual !== "index.html")
			window.location = "index.html";
	}
}

//#region formularios
function permite(elEvento, permitidos) {
  // Variables que definen los caracteres permitidos
  let numeros = "0123456789";
  let caracteres = " aábcdeéfghiíjklmnñoópqrstuúvwxyzAÁBCDEÉFGHIÍJKLMNÑOÓPQRSTUÚVWXYZ";
  let numeros_caracteres = numeros + caracteres;
  let teclas_especiales = [8, 9, 45, 46, 64, 95];
  // 8 = BackSpace, 46 = Supr, 45 = guion medio, 64 = @, 95 = guion bajo
  // Seleccionar los caracteres a partir del parámetro de la función
  switch(permitidos) {
    case 'num':
      permitidos = numeros;
      break;
    case 'car':
      permitidos = caracteres;
      break;
    case 'num_car':
      permitidos = numeros_caracteres;
      break;
  }
  // Obtener la tecla pulsada 
  let evento = elEvento;
  let codigoCaracter = evento.charCode || evento.keyCode;
  let caracter = String.fromCharCode(codigoCaracter);
  // Comprobar si la tecla pulsada es alguna de las teclas especiales
  // (teclas de borrado y flechas horizontales)
  let tecla_especial = false;
  for(var i in teclas_especiales) {
    if(codigoCaracter == teclas_especiales[i]) {
      tecla_especial = true;
      break;
    }
  }
  // Comprobar si la tecla pulsada se encuentra en los caracteres permitidos
  // o si es una tecla especial
  return permitidos.indexOf(caracter) != -1 || tecla_especial;
}
//Mostrar y ocultar password
function tooglePassType(input){
	let type = document.querySelector("#" + input).type;
	let eyeIcon = document.querySelector("#" + input + "Show");
	if (type == 'password'){
		document.querySelector("#" + input).type = "text";
		eyeIcon.src = "img/eyeClose.png";
	} else {
		document.querySelector("#" + input).type = "password";
		eyeIcon.src = "img/eyeOpen.png";
	}
}
//Enviar datos
function sendData(act,data,fn,met){
	const getSend = {
		method: "GET",
	}
	const normalJsonSend = {
		method: "POST",
		headers: {
			"Content-Type": "application/json"
		},
		body: JSON.stringify(data)
	}
	const fileSend = {
		method: "POST",
		body: data
	}
	let options = normalJsonSend;
	switch (met){
		case 'file' : options = fileSend; break;
		case 'GET' : options = getSend; break;
	}
	// Mostrar mensaje cargando
	let loadingMsg = document.querySelector(".loadingMsg");
	if (loadingMsg != null) loadingMsg.style.display = "flex";
	fetch(act, options)
		.then(response => {
			// Eliminamos mensaje Cargando
			if (loadingMsg != null) loadingMsg.style.display = "none";
			if (!response.ok){
				if (response.status === 403){
					window.location = "./index.html";
				}
				console.log("error");
			}
			return response.text();
		})
		.then(data => {
			if (fn !=null){
				// Si la sesion está cerrada redirigir y no enviar la callback
				if ((data != null) && SESSION_OUT.includes(JSON.parse(data)))
					window.location = "index.html";
				else
					eval(fn + '(' + data + ')');
			}
		})
		.catch(err => {
			// Eliminar mensaje Cargando
			if (loadingMsg != null) loadingMsg.style.display = "none";
			console.error("ERROR de fetch: ", err.message);
		});
}

//Enviar formulario 
function sendForm(act,form,fn,met){
	//let formulario = document.querySelector("#" + form);
	// Validacion de formularios
	if (validacionFormularios(form)){
		var data = mySerializeArray(form, met);
		sendData(act,data,fn,met);
	} else {
		let mensaje = "<p class='textoAlert'>Todos los campos son obligatorios</p>";
		sweetalertError("Error en el formulario",mensaje,"Entendido");
	}
}

const mySerializeArray = (myForm, met) => {
	let formData = new FormData(document.getElementById(myForm));
	var obj = Object.fromEntries(Array.from(formData.keys()).map(key => [
		key, formData.getAll(key).length > 1 ? formData.getAll(key) : formData.get(key)
	]));
	return (met == "file") ? formData : obj;
}
function validacionFormularios(form){
	let valido = false;
	// Creamos una lista de formularios excluidos
	// En algunos casos nos interesa una validación mas concreta
	const FORMULARIOS_EXCLUIDOS = [
		'formServicioCuentas',
	];
	if(FORMULARIOS_EXCLUIDOS.includes(form)){
		// Se realizará validación de campos concretos unicamente
		valido = true;
	} else {
		// Se invoca la validación nativa de HTML como si fuera un .submit()
		let formulario = document.querySelector("#" + form);
		valido = formulario.reportValidity();
	}
	return valido;
}
//Formatear saldo a euros
function aplicarFormatoEuro(idInput){
    const input = document.getElementById(idInput);

    if (!input) return;
    input.addEventListener("input", function () {
        let valor = this.value;
        valor = valor.replace(/[^\d,\.]/g, "");
		valor = valor.replace(/\./g, "");
        valor = valor.replace(",", ".");

        let numero = parseFloat(valor);

		if (isNaN(numero) || numero <= 0) {
            this.dataset.valorReal = "";
			input.value = "";
            return;
        }

        this.dataset.valorReal = numero;
    });

    input.addEventListener("blur", function () {
        let numero = parseFloat(this.dataset.valorReal);

        if (!isNaN(numero)) {
            this.value = numero.toLocaleString("es-ES", {
                style: "currency",
                currency: "EUR"
            });
        }
    });

    input.addEventListener("focus", function () {
        if (this.dataset.valorReal) {
            this.value = this.dataset.valorReal;
        }
    });
}
//Formatear vista de saldo en euros
function aplicarVistaEuros(valorReal) {
    // Quitar símbolo € y espacios
    let valor = valorReal.toString().replace(/[€\s]/g, "");

    // Convertir a número
    let numero = parseFloat(valor);
    if (isNaN(numero)) return "";

    // Formatear a español
    return numero.toLocaleString("es-ES", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }) + "€";
}
//#endregion 

//#region CUENTAS
// Generar lista de cuentas en Mis cuentas
function generarListaCuentas(){
	let listaCuentas = document.querySelector("#menuSeleccionarCuenta ul.lista");
	// Ocultamos el mensaje de error
	document.querySelector(".sinCuentas").style.display = "none";
	// Borramos la lista demo
	listaCuentas.innerHTML = "";
	// Recorremos el array que trae las cuentas y que está dentro de datos
	listaCuentasGlobal.forEach((cuenta) => {
		// Creamos un elemento li
		let lineaCuenta = document.createElement("li");
		// Le ponermos el nombre de la cuenta como texto
		lineaCuenta.textContent = cuenta.nombre_cuenta;
		// Le ponemos un id que es un sufijo (para evitar erroes y coincidencias) y el id de la cuenta. Así lo tenemos guardado para operar
		lineaCuenta.id = "cuenta" + cuenta.id_cuenta;
		// Añadimos la clase
		lineaCuenta.classList.add("cuenta-item");
		// Lo añadimos al DOM dentro de la lista
		listaCuentas.appendChild(lineaCuenta);
		// Le asignamos evento clic
		lineaCuenta.addEventListener("click", manejarCuenta.bind(null, cuenta));
	});
}
// Funciones asociadas a eventos de ratón que ha convenido 
// implementar fuera de una función anónima para su reutilización
// Acciones asociadas cuando se hace click en una cuenta de la lista
function manejarCuenta(cuenta){
	//console.log("entra");
	//console.log(cuenta);
	//console.log("Has seleccionado: " + cuenta.nombre_cuenta + "cuenta:" + cuenta);
	// Mostramos la cuenta a la que hacemos click
	mostrarMenuCuentaSeleccionada();
	// Se añaden los datos a la vista de cuenta
	let nombreCuenta = document.querySelector("#cuentaActual h2.nombreCuenta");
	let mostrarSaldoCuenta = document.querySelector("#cuentaActual p.saldo");
	let idCuenta = document.querySelector("#idCuenta");
	let saldoCuenta = document.querySelector("#saldoCuenta");
	nombreCuenta.innerHTML = "Cuenta <span class='nombreCuenta'>" + cuenta.nombre_cuenta + "<span>";
	mostrarSaldoCuenta.textContent = aplicarVistaEuros(cuenta.saldo_cuenta);
	//saldoCuenta.textContent = cuenta.saldo_cuenta + " €";
	idCuenta.value = cuenta.id_cuenta;
	saldoCuenta.value = aplicarVistaEuros(cuenta.saldo_cuenta);
}
// Se muestra el servicio cuentas y se solicita la lista actualizada de las mismas
function gestionServicioCuentas(){
	mostrarServicioCuentas();
	pedirListaCuentas();
}
// Solicitudes a los microservicios que se han implementado como función a parte
// Esto conviene cuando se desean realizar acciones de control justo antes de enviar la peticion
// y tras haber pulsado el botón de acción del formuario
//
// Solicitar lista de cuentas
function pedirListaCuentas(){
	// Solicitamos la información de cuentas para hacer la lista
	// Almacenamos el token en un objeto JSON para enviar la solicitud sendData
	datos = {
		'token' : localStorage.getItem('jwt_token')
	}
	sendData('/api/cuentas.php/leer-cuentas',datos,'TrasLeerCuentas','POST');
}

// Solicitud de modificacion
function solicitarModificacion(){
	let nuevoNombreCuenta = document.querySelector("#nuevoNombreCuenta");
	let nuevoSaldoCuenta = document.querySelector("#nuevoSaldoCuenta");
	if (nuevoNombreCuenta.value !== "" && nuevoSaldoCuenta.value !== ""){
		document.querySelector("#crearCuentaToken").value = localStorage.getItem('jwt_token');
		sendForm('/api/cuentas.php/modificar-cuenta','formServicioCuentas','TrasModificarCuenta','POST');
	} else {
		alert(" Los campos no pueden estar vacíos");
	}
}

// Preparacion formulario de transferencias
function prepararFormularioTrasferencias(){
	// Primero se generan las cuentas destino posibles
	let selectCuentaDestino = document.querySelector("#opcionesDestinoTransferencia");
	// Se vacía el select por si tiene algo
	selectCuentaDestino.innerHTML = "";
	// Se obtiene el valor de cuenta actual y se conviernte a Int para la comparación
	let cuentaActual = parseInt(document.querySelector("#idCuenta").value);
	// Se construye el select evitando la cuenta actual como cuenta de destino
	// El primer elemento es el de elegir cuenta
	let elementoOption = document.createElement("option");
	elementoOption.value = "";
	elementoOption.textContent = "-- Elgir Cuenta --";
	selectCuentaDestino.appendChild(elementoOption);
	// Aquí generamos el resto de cuentas elegibles
	listaCuentasGlobal.forEach((cuenta) => {	
		if (cuentaActual !== cuenta.id_cuenta){
			elementoOption = document.createElement("option");
			elementoOption.value = cuenta.id_cuenta;
			elementoOption.textContent = cuenta.nombre_cuenta;
			selectCuentaDestino.appendChild(elementoOption);
		}
	});
	
}
function enviarTransferencia(){
	let transferCuentaDestino = document.querySelector("#opcionesDestinoTransferencia");
	let transferImporte = document.querySelector("#montoTransferir");
	let transferConcepto = document.querySelector("#descripcionTransferir");

	if (transferCuentaDestino.value !== "" && transferImporte.value !== "" && transferConcepto.value !== ""){
		document.querySelector("#crearCuentaToken").value = localStorage.getItem('jwt_token');
		document.querySelector("#nuevoNombreCuenta").value = document.querySelector("#cuentaActual span.nombreCuenta").textContent;
		sendForm('/api/operaciones.php/transferir','formServicioCuentas','TrasEnviarTransferencia','POST');
	} else {
		// Muestro un alert para que revisen el formulario
		let mensaje = "<p class='textoAlert'>Todos los campos son obligatorios</p>";
		sweetalertError("Error en el formulario",mensaje,"Entendido");
	}
}

function enviarIngreso(){
	let cantidadIngreso = document.querySelector("#montoIngresar");
	let descripcion = document.querySelector("#descripcionIngreso");
	let catergoria = document.querySelector("#categoriaIngreso");

	if (cantidadIngreso.value !== "" && descripcion.value !== "" && catergoria.value !== ""){
		document.querySelector("#crearCuentaToken").value = localStorage.getItem('jwt_token');

		let nombreCuenta = document.querySelector("#cuentaActual span.nombreCuenta");
		let nuevoNombreCuenta = document.querySelector("#nuevoNombreCuenta");
		nuevoNombreCuenta.value = nombreCuenta.textContent;

		sendForm('/api/operaciones.php/ingresar','formServicioCuentas','TrasEnviarIngreso','POST');
	} else {
		// Muestro un alert para que revisen el formulario
		let mensaje = "<p class='textoAlert'>Todos los campos son obligatorios</p>";
		sweetalertError("Error en el formulario",mensaje,"Entendido");
	}
}

function enviarGasto(){
	let cantidadGasto = document.querySelector("#montoGastar");
	let descripcion = document.querySelector("#descripcionGasto");
	let catergoria = document.querySelector("#categoriaGasto");

	if (cantidadGasto.value !== "" && descripcion.value !== "" && catergoria.value !== ""){
		document.querySelector("#crearCuentaToken").value = localStorage.getItem('jwt_token');
		let nombreCuenta = document.querySelector("#cuentaActual span.nombreCuenta");
		let nuevoNombreCuenta = document.querySelector("#nuevoNombreCuenta");
		nuevoNombreCuenta.value = nombreCuenta.textContent;

		sendForm('/api/operaciones.php/gastar','formServicioCuentas','TrasEnviarGasto','POST');
	} else {
		// Muestro un alert para que revisen el formulario
		let mensaje = "<p class='textoAlert'>Todos los campos son obligatorios</p>";
		sweetalertError("Error en el formulario",mensaje,"Entendido");
	}
}
function solicitarOperaciones(){
	//leer cuentas y mostrar sus operaciones
	datos = {
		'token' : localStorage.getItem('jwt_token')
	}
	sendData('/api/cuentas.php/leer-cuentas',datos,'LeerCuentasParaMostrarOperaciones','POST');
}

// Análisis
function solicitarAnalisis(){
	pedirListaCuentas(); //-> generarTablaPatrimonio();
}

function generarTablaPatrimonio(){
		const tabla = document.querySelector("#finanzasPatrimonio ul.tabla-patrimonio");

		if (!tabla) return;
		// Limpiar contenido previo (excepto cabecera)
		tabla.innerHTML = `
			<li class="cabecera-patrimonio">
				<span>Cuenta</span>
				<span>Tipo</span>
				<span>Saldo</span>
			</li>
		`;
		let total = 0;

		listaCuentasGlobal.forEach(cuenta => {
			let li = document.createElement("li");

			let saldo = parseFloat(cuenta.saldo_cuenta);
			total += saldo;

			li.innerHTML = `
				<span>${cuenta.nombre_cuenta}</span>
				<span class="tipo-${cuenta.tipo_cuenta}">${cuenta.tipo_cuenta}</span>
				<span class="${saldo >= 0 ? 'saldo-positivo' : 'saldo-negativo'}">
					${aplicarVistaEuros(saldo)}
				</span>
			`;
			tabla.appendChild(li);
		});

		// Añadir fila total
		let liTotal = document.createElement("li");
		liTotal.classList.add("pie-patrimonio");

		liTotal.innerHTML = `
			<span>Total</span>
			<span></span>
			<span>
				${aplicarVistaEuros(total)}
			</span>
		`;

		tabla.appendChild(liTotal);
	}

	function calcularCapacidadAhorro(){
		
		let listaIdsCuentas = listaCuentasGlobal.map(cuenta => cuenta.id_cuenta);
		if (listaCuentasGlobal.length > 0){
			let datos = {
				'token' : localStorage.getItem('jwt_token'),
				'cuentas' : listaIdsCuentas,
				//"filtros": {"fecha_inicio": "últimos 30 días"}
			}
			sendData('/api/analisis.php/capacidad-ahorro',datos,'TrasConsultarAhorro','POST');
		}
		else
			{
				let mensaje = "<p>No tienes ninguna cuenta creada</p>";
				sweetalertInfo("Analisis",mensaje,"Entendido");
			}	
	}
//#endregion

//#region callbacks
//Callbacks para las funciones realizadas tras una solicitud a un microservicio
// login
function operacionesTrasLogin(datos){
	document.querySelector("#log_error").style.display = "none";
	if (datos !== "" || datos !== null){
		// Si hay error se muestra el mensaje. Si no, se redirecciona al menu que comprobará la sesión por su cuenta
		if (MENSAJES_ERROR.includes(datos.mensaje)){
			document.querySelector("#log_error").style.display = "block";
		} else {
			localStorage.setItem('jwt_token', datos.token);
			// Se almacena el id de usuario
			datosUsuario = datos.id_usuario;
			window.location = "menuServicios.html";
		}
	}
}
// Comprobacion de sesion
function accionesTrasTestToken(datos){
	if (datos !== "" || datos !== null){
		// Si hay error se muestra el mensaje. Si no, se redirecciona al menu que comprobará la sesión por su cuenta
		if (MENSAJES_ERROR.includes(datos.mensaje)){
			let direccionActual = window.location.href.split('/'.toLowerCase()).slice(-1)[0];
			if (direccionActual !== "index.html")
				window.location = "index.html";
		} else {
			let direccionActual = window.location.href.split('/'.toLowerCase()).slice(-1)[0];
			if (direccionActual !== "menuServicios.html")
				window.location = "menuServicios.html";
		}
	}
}
// Registro
function operacionesTrasRegistro(datos){
	document.querySelector("#log_error").style.display = "none";
	if (datos !== "" || datos !== null){
		// Si hay error muestro el mensaje. Si no hay redirecciono al inicio para que muestre el form de login
		if (MENSAJES_ERROR.includes(datos.estado)){
			let mensaje = "<p>" + datos.mensaje + "</p>";
			sweetalertError("Registrar usuario",mensaje,"Entendido",null);
		} else {
			console.log("registro ok");
			window.location = "index.html";

		}
	}
}
// Leer usuario
function accionesUsuarioLeido(datos){
	if (datos !== "" || datos !== null){
		// Si hay error muestro el mensaje. Si no hay redirecciono al inicio para que muestre el form de login
		if (MENSAJES_ERROR.includes(datos.mensaje)){
			let mensaje = "<p>" + datos.mensaje + "</p>";
			sweetalertError("Modificar datos de usuario",mensaje,"Entendido",null);
		} else {
			console.log("usuario leido");
			let nombreUsuario = document.querySelector('#nombreUsuario');
			let apellidosUsuario = document.querySelector('#apellidosUsuario');
			let emailUsuario = document.querySelector('#emailUsuario');
			let telefonoUsuario = document.querySelector('#telefonoUsuario');
			let passwordUsuario = document.querySelector('#passwordUsuario');
			let token = document.querySelector('#modificarUsuarioToken');

			nombreUsuario.value = datos.datosUsuario.nombre;
			apellidosUsuario.value = datos.datosUsuario.apellidos;
			emailUsuario.value = datos.datosUsuario.email;
			telefonoUsuario.value = datos.datosUsuario.telefono;
			passwordUsuario.value = "";
			token.value = localStorage.getItem('jwt_token');
		}
	}
}
// Modificar usuario
function operacionesTrasModificar(datos){
	if (datos !== "" || datos !== null){
		// Si hay error muestro el mensaje. Si no hay redirecciono al inicio para que muestre el form de login
		if (MENSAJES_ERROR.includes(datos.mensaje)){
			let mensaje = "<p>" + datos.mensaje + "</p>";
			sweetalertError("Modificar datos de usuario",mensaje,"Entendido",null);
		} else {
			console.log("ok");
			sweetalertInfo("Modificar datos de usuario","mensaje","Entendido",mostrarMenuServicios);
		}
	}
}

// Leer cuentas
function TrasLeerCuentas(datos){
	if (datos !== "" || datos !== null){
		//console.log(datos);
		let listaCuentas = document.querySelector("#menuSeleccionarCuenta ul.lista");
		if (MENSAJES_ERROR.includes(datos.mensaje)){
			// Inyectaremos un mesaje en el div de la lista para poner que no hay elementos para mostrar
			listaCuentas.style.display = "none";
			if (ERROR_CONEXION_BASEDATOS.includes(datos.mensaje)){
				let mensaje = "<p>En estos momentos no podemos acceder a sus datos de cuentas. Por favor, inténtelo más tarde.</p>";
				sweetalertError("Problema de conexión",mensaje,"Entendido",null);
			}
		} else {
			// Almacenamos la lista tenerla a mano
			listaCuentasGlobal = datos.listaCuentas;
			generarListaCuentas();
			generarTablaPatrimonio();
		}
	}
}
// Crear cuenta
function TrasCrearCuenta(datos){
	if (datos !== "" || datos !== null){
		if (MENSAJES_ERROR.includes(datos.mensaje)){
			// Manejo de errores
			if (ERROR_CONEXION_BASEDATOS.includes(datos.mensaje)){
				let mensaje = "<p>En estos momentos no podemos acceder a sus datos de cuentas. Por favor, inténtelo más tarde.</p>";
				sweetalertError("Problema de conexión",mensaje,"Entendido",null);
			} else {
				let mensaje = "<p>" + datos.mensaje + "</p>";
				sweetalertError("Modificar cuenta",mensaje,"Entendido",null);
			}
		} else {
			// Tenemos que restablecer el formulario de crecion 
			document.querySelector("#nuevaCuenta").value = "";
			document.querySelector("#saldoNuevaCuenta").value = "";
			document.querySelector("#selectorTipoCuenta").value = "";
			// Solicitamos la lista de cuentas actualizada
			datos = {
				'token' : localStorage.getItem('jwt_token')
			}
			sendData('/api/cuentas.php/leer-cuentas',datos,'TrasLeerCuentas','POST');
		}
	}
}
// Modificar cuenta
function TrasModificarCuenta(datos){
	if (datos !== "" || datos !== null){
		if (MENSAJES_ERROR.includes(datos.mensaje)){
			// Manejo de errores
			if (ERROR_CONEXION_BASEDATOS.includes(datos.mensaje)){
				let mensaje = "<p>En estos momentos no podemos acceder a sus datos de cuentas. Por favor, inténtelo más tarde.</p>";
				sweetalertError("Problema de conexión",mensaje,"Entendido",null);
			} else {
				let mensaje = "<p>Error al modificar la cuenta</p>";
				sweetalertError("Modificar cuenta",mensaje,"Entendido");
			}
		} else {
			// Se volverá atrás tras restablecer el formulario 
			document.querySelector("#nuevoNombreCuenta").value = "";
			document.querySelector("#nuevoSaldoCuenta").value = "";
			let mensaje = "<p>Cuenta modificada con éxito</p>";
			sweetalertInfo("Modificar cuenta",mensaje,"Entendido",manejarCuenta,datos.datosCuenta);
		}
	}
}
// Borrar cuenta
function TrasBorrarCuenta(){
	if (datos !== "" || datos !== null){
		if (MENSAJES_ERROR.includes(datos.mensaje)){
			// Manejo de errores
			if (ERROR_CONEXION_BASEDATOS.includes(datos.mensaje)){
				let mensaje = "<p>En estos momentos no podemos acceder a sus datos de cuentas. Por favor, inténtelo más tarde.</p>";
				sweetalertError("Problema de conexión",mensaje,"Entendido",null);
			} else {
				let mensaje = "<p>Error al borrar la cuenta</p>";
				sweetalertError("Borrar cuenta",mensaje,"Entendido");
			}
		} else {
			// Se volverá atrás tras restablecer el formulario 
			let mensaje = "<p>Cuenta eliminada con éxito</p>";
			sweetalertInfo("Borrar cuenta",mensaje,"Entendido",gestionServicioCuentas);
		}
	}
}
// Gasto
function TrasEnviarGasto(datos){
	if (datos !== "" || datos !== null){
		if (MENSAJES_ERROR.includes(datos.mensaje)){
			// manejar este error
			// Se volverá atrás tras restablecer el formulario 
			//let mensaje1 = datos.mensaje;
			let mensaje = "<p>" + datos.mensaje + "</p>";
			sweetalertError("Añadir gasto",mensaje,"Entendido");
		} else {
			// Se volverá atrás tras restablecer el formulario 
			document.querySelector("#montoGastar").value = "";
			document.querySelector("#descripcionGasto").value = "";
			document.querySelector("#categoriaGasto").value = "";
			let mensaje = "<p>Gasto añadido con éxito</p>";
			sweetalertInfo("Añadir gasto",mensaje,"Entendido",manejarCuenta,datos.datosCuenta);
		}
	}
}
// Ingreso
function TrasEnviarIngreso(datos){
	if (datos !== "" || datos !== null){
		if (MENSAJES_ERROR.includes(datos.mensaje)){
			// manejar este error
			// Se volverá atrás tras restablecer el formulario 
			let mensaje = "<p>Error al realizar el ingreso</p>";
			sweetalertError("Añadir Ingreso",mensaje,"Entendido");
		} else {
			// Se volverá atrás tras restablecer el formulario 
			document.querySelector("#montoIngresar").value = "";
			document.querySelector("#descripcionIngreso").value = "";
			document.querySelector("#categoriaIngreso").value = "";
			let mensaje = "<p>Ingreso añadido con éxito</p>";
			sweetalertInfo("Añadir Ingreso",mensaje,"Entendido",mostrarMenuServicios);
		}
	}
}

function LeerCuentasParaMostrarOperaciones(datos){
	if (datos !== "" || datos !== null){
		if (MENSAJES_ERROR.includes(datos.mensaje)){
			let mensaje = "<p>Error al leer la lista de cuentas</p>";
			sweetalertError("Operaciones de tus cuentas",mensaje,"Entendido");
		} else {
			// Almacenamos la lista para tenerla a mano
			listaCuentasGlobal = datos.listaCuentas;
			// Extraemos solo los ids de las cuentas
			if (listaCuentasGlobal.length > 0){
				let listaIdsCuentas = listaCuentasGlobal.map(cuenta => cuenta.id_cuenta);
				datos = {
					'token' : localStorage.getItem('jwt_token'),
					'cuentas' : listaIdsCuentas
				}
				sendData('/api/operaciones.php/leer-operaciones',datos,'trasLeerOperaciones','POST');
			} else {
				let mensaje = "<p>No tienes ninguna cuenta creada</p>";
				sweetalertInfo("Tus cuentas",mensaje,"Entendido",manejarCuenta,datos.datosCuenta);
			}
		}
	}
}

function trasLeerOperaciones(datos){
	if (datos !== "" || datos !== null){
		if (MENSAJES_ERROR.includes(datos.mensaje)){
			let mensaje = "<p>Error al leer operaciones</p>";
			sweetalertError("Leer Operaciones",mensaje,"Entendido");
		} else {
			// Se compone la tabla de operaciones
			let tablaOperaciones = document.querySelector("#servicioOperaciones ul.tabla-operaciones");
			// Se vacía la tabla por si hay algo anterior
			tablaOperaciones.innerHTML = "";
			// Generamos la cabecera y la inyectamos en el DOM
			let cabecera = document.createElement("li");
			cabecera.classList.add('cabecera');
			cabecera.innerHTML = "<span>Fecha</span><span>Cuenta</span><span>Tipo</span><span>Categoría</span><span>Importe</span><span>Descripción</span>";
			tablaOperaciones.appendChild(cabecera);
			// Generamos las líneas y las inyectamos en el DOM
			datos.listaOperaciones.forEach((operacion) => {
				let lineaOperacion = document.createElement("li");
				let idCuenta = operacion.id_cuenta_operacion;

				const cuenta = listaCuentasGlobal.find(c => c.id_cuenta === idCuenta);
				const nombreCuenta = cuenta ? cuenta.nombre_cuenta : null;

				let claseImporte = "";
				// Damos formato al importe
				switch (operacion.tipo){
					case 'gasto': {
						importe = "-" + operacion.monto;
						claseImporte = "importe_negativo";
					};break;
					case 'ingreso': {
						importe = "+" + operacion.monto;
						claseImporte = "importe_positivo";
					};break;
					default: importe = operacion.monto;break;
				}
				// Generamos la tabla
				lineaOperacion.innerHTML = `<span>${operacion.fecha_operacion.split(' ')[0]}</span>
											<span>${nombreCuenta}</span>
											<span>${operacion.tipo_operacion}</span>
											<span>${operacion.tipo_categoria}</span>
											<span class="${claseImporte}">${importe}</span>
											<span>${operacion.descripcion_operacion}</span>`.replace(/>\s+</g, '><').trim();
											// Añadimos este replace para que no nos añada elementos no deseados
				tablaOperaciones.appendChild(lineaOperacion);
			});
		}
	}
}
function TrasEnviarTransferencia(datos){
	if (datos !== "" || datos !== null){
		if (MENSAJES_ERROR.includes(datos.mensaje)){
			let mensaje = "<p>"+ datos.mensaje + "</p>";
			sweetalertError("Transferencia",mensaje,"Entendido");
		} else {
			document.querySelector("#opcionesDestinoTransferencia").value = "";
			document.querySelector("#montoTransferir").value = "";
			document.querySelector("#descripcionTransferir").value = "";
			let mensaje = "<p>Transferencia realizada con éxito</p>";
			sweetalertInfo("Transferencia",mensaje,"Entendido",mostrarMenuServicios);
		}
	}
}

function TrasConsultarAhorro(datos){
	const boton = document.querySelector("#btnAnalisisAhorro");
	const cantidadAhorro = document.querySelector("#analisis-ahorro");
	
	if (datos < 0){
		cantidadAhorro.classList.add("importe_negativo");
	}
	// Quito el boón y muestro resultado
	boton.style.display = "none";
	cantidadAhorro.style.display = "block";	
	cantidadAhorro.textContent = aplicarVistaEuros(datos);
		//cantidadAhorro.innerHTML = datos;
}
//#endregion


// Mensajes Alert mejorados
function sweetalertInfo(title, html, buttontext, confirmCB = null, param = null){
	Swal.fire({
		title: "<strong>" + title + "</strong>",
		icon: "success",
		html: `<div class="cbuploadfiles">${html}</div>`,
		showCloseButton: true,
		confirmButtonText: buttontext,
		confirmButtonColor: '#93282c',
		customClass: {
			confirmButton: 'butLogin'
		},
		buttonsStyling: false
	}).then((result) => {
		if (result.isConfirmed && typeof confirmCB === 'function')
			confirmCB(param);
	}); 
}
function sweetalertError(title, html, buttontext, confirmCB = null, param = null){
	Swal.fire({
		title: "<strong>" + title + "</strong>",
		icon: "error",
		html: `<div class="cbuploadfiles">${html}</div>`,
		showCloseButton: true,
		confirmButtonText: buttontext,
		confirmButtonColor: '#93282c',
		customClass: {
			confirmButton: 'butLogin'
		},
		buttonsStyling: false
	}).then((result) => {
		if (result.isConfirmed && typeof confirmCB === 'function')
			confirmCB(param);
	}); 
}
//Operaciones

