<?php
/**
 * Doble mínimo del SDK de Google (Photos y Calendar).
 *
 * El SDK no está instalado y no hace falta para comprobar lo que hace Convoca con él: lo que importa es **qué pide**
 * (título del álbum, evento con sus horas) y **qué hace con la respuesta** (guardar el id, la URL, avisar). Cada
 * llamada se apunta en `$GLOBALS['__sdk_llamadas']` y las respuestas se fijan en `$GLOBALS['__sdk']`.
 *
 * @package Convoca\Enroll
 */

namespace Google\Service {

	if ( ! class_exists( '\Google\Service\PhotosLibrary', false ) ) {

		/**
		 * Colección de álbumes.
		 */
		class PhotosLibraryAlbums {

			/**
			 * Crea un álbum.
			 *
			 * @param object $album Álbum pedido.
			 * @return object
			 *
			 * @throws \Exception Si la prueba pide que falle.
			 */
			public function create( $album ) {
				$GLOBALS['__sdk_llamadas'][] = array( 'album_crear', $album->getTitle() );

				if ( ! empty( $GLOBALS['__sdk']['falla_crear'] ) ) {
					throw new \Exception( 'la API dijo que no' );
				}

				$nuevo = new \Google\Service\PhotosLibrary\Album();
				$nuevo->setTitle( $album->getTitle() );
				$nuevo->setId( $GLOBALS['__sdk']['album_id'] ?? 'ALBUM-1' );
				$nuevo->setProductUrl( $GLOBALS['__sdk']['album_url'] ?? 'https://photos.example.test/a/1' );

				return $nuevo;
			}

			/**
			 * Comparte un álbum.
			 *
			 * @param string $album_id Álbum.
			 * @param object $peticion Petición.
			 * @return object
			 *
			 * @throws \Exception Si la prueba pide que falle.
			 */
			public function share( $album_id, $peticion ) {
				unset( $peticion );
				$GLOBALS['__sdk_llamadas'][] = array( 'album_compartir', $album_id );

				if ( ! empty( $GLOBALS['__sdk']['falla_compartir'] ) ) {
					throw new \Exception( 'la API dijo que no' );
				}

				$info = new \Google\Service\PhotosLibrary\ShareInfo();
				$info->setShareableUrl( $GLOBALS['__sdk']['share_url'] ?? 'https://photos.example.test/share/1' );

				$respuesta = new \Google\Service\PhotosLibrary\ShareAlbumResponse();
				$respuesta->setShareInfo( $info );

				return $respuesta;
			}
		}

		/**
		 * Servicio de Photos.
		 */
		class PhotosLibrary {

			/**
			 * Álbumes.
			 *
			 * @var PhotosLibraryAlbums
			 */
			public $albums;

			/**
			 * Constructor.
			 *
			 * @param object|null $client Cliente.
			 */
			public function __construct( $client = null ) {
				unset( $client );
				$this->albums = new PhotosLibraryAlbums();
			}
		}
	}
}

namespace Google\Service\PhotosLibrary {

	if ( ! class_exists( '\Google\Service\PhotosLibrary\Album', false ) ) {

		/**
		 * Álbum.
		 */
		class Album {

			/** @var string */
			private $title = '';
			/** @var string */
			private $id = '';
			/** @var string */
			private $product_url = '';

			/**
			 * Título.
			 *
			 * @param string $valor Título.
			 * @return void
			 */
			public function setTitle( $valor ) {
				$this->title = (string) $valor;
			}

			/**
			 * Título.
			 *
			 * @return string
			 */
			public function getTitle() {
				return $this->title;
			}

			/**
			 * Id.
			 *
			 * @param string $valor Id.
			 * @return void
			 */
			public function setId( $valor ) {
				$this->id = (string) $valor;
			}

			/**
			 * Id.
			 *
			 * @return string
			 */
			public function getId() {
				return $this->id;
			}

			/**
			 * URL pública.
			 *
			 * @param string $valor URL.
			 * @return void
			 */
			public function setProductUrl( $valor ) {
				$this->product_url = (string) $valor;
			}

			/**
			 * URL pública.
			 *
			 * @return string
			 */
			public function getProductUrl() {
				return $this->product_url;
			}
		}

		/**
		 * Petición de compartir.
		 */
		class ShareAlbumRequest {}

		/**
		 * Información de compartir.
		 */
		class ShareInfo {

			/** @var string */
			private $url = '';

			/**
			 * URL para compartir.
			 *
			 * @param string $valor URL.
			 * @return void
			 */
			public function setShareableUrl( $valor ) {
				$this->url = (string) $valor;
			}

			/**
			 * URL para compartir.
			 *
			 * @return string
			 */
			public function getShareableUrl() {
				return $this->url;
			}
		}

		/**
		 * Respuesta de compartir.
		 */
		class ShareAlbumResponse {

			/** @var ShareInfo */
			private $info;

			/**
			 * Información.
			 *
			 * @param ShareInfo $valor Información.
			 * @return void
			 */
			public function setShareInfo( $valor ) {
				$this->info = $valor;
			}

			/**
			 * Información.
			 *
			 * @return ShareInfo
			 */
			public function getShareInfo() {
				return $this->info;
			}
		}
	}
}

namespace Google\Service\Calendar {

	if ( ! class_exists( '\Google\Service\Calendar\EventDateTime', false ) ) {

		/**
		 * Fecha/hora de un evento.
		 */
		class EventDateTime {

			/** @var string */
			private $datetime = '';

			/**
			 * Fecha ISO.
			 *
			 * @param string $valor Fecha.
			 * @return void
			 */
			public function setDateTime( $valor ) {
				$this->datetime = (string) $valor;
			}

			/**
			 * Fecha ISO.
			 *
			 * @return string
			 */
			public function getDateTime() {
				return $this->datetime;
			}

			/**
			 * Zona horaria.
			 *
			 * @param string $valor Zona.
			 * @return void
			 */
			public function setTimeZone( $valor ) {
				unset( $valor );
			}
		}

		/**
		 * Evento.
		 */
		class Event {

			/**
			 * Constructor: el SDK real acepta el array de datos (`summary`, `description`, `location`,
			 * `start`/`end` como arrays) y de el saca las propiedades. Sin esto, el producto parecia
			 * enviar un evento vacio.
			 *
			 * @param array $datos Datos del evento.
			 */
			public function __construct( $datos = array() ) {
				if ( ! is_array( $datos ) ) {
					return;
				}
				$this->summary     = (string) ( $datos['summary'] ?? '' );
				$this->description = (string) ( $datos['description'] ?? '' );
				$this->location    = (string) ( $datos['location'] ?? '' );

				foreach ( array( 'start' => 'start', 'end' => 'end' ) as $clave => $propiedad ) {
					if ( empty( $datos[ $clave ]['dateTime'] ) ) {
						continue;
					}
					$cuando = new \Google\Service\Calendar\EventDateTime();
					$cuando->setDateTime( $datos[ $clave ]['dateTime'] );
					$this->$propiedad = $cuando;
				}
			}

			/** @var string */
			private $summary = '';
			/** @var string */
			private $description = '';
			/** @var string */
			private $location = '';
			/** @var string */
			private $id = '';
			/** @var string */
			private $html_link = '';
			/** @var EventDateTime */
			private $start;
			/** @var EventDateTime */
			private $end;

			/**
			 * Título.
			 *
			 * @param string $valor Título.
			 * @return void
			 */
			public function setSummary( $valor ) {
				$this->summary = (string) $valor;
			}

			/**
			 * Título.
			 *
			 * @return string
			 */
			public function getSummary() {
				return $this->summary;
			}

			/**
			 * Descripción.
			 *
			 * @param string $valor Texto.
			 * @return void
			 */
			public function setDescription( $valor ) {
				$this->description = (string) $valor;
			}

			/**
			 * Descripción.
			 *
			 * @return string
			 */
			public function getDescription() {
				return $this->description;
			}

			/**
			 * Lugar.
			 *
			 * @param string $valor Lugar.
			 * @return void
			 */
			public function setLocation( $valor ) {
				$this->location = (string) $valor;
			}

			/**
			 * Lugar.
			 *
			 * @return string
			 */
			public function getLocation() {
				return $this->location;
			}

			/**
			 * Inicio.
			 *
			 * @param EventDateTime $valor Inicio.
			 * @return void
			 */
			public function setStart( $valor ) {
				$this->start = $valor;
			}

			/**
			 * Inicio.
			 *
			 * @return EventDateTime
			 */
			public function getStart() {
				return $this->start;
			}

			/**
			 * Fin.
			 *
			 * @param EventDateTime $valor Fin.
			 * @return void
			 */
			public function setEnd( $valor ) {
				$this->end = $valor;
			}

			/**
			 * Fin.
			 *
			 * @return EventDateTime
			 */
			public function getEnd() {
				return $this->end;
			}

			/**
			 * Id.
			 *
			 * @param string $valor Id.
			 * @return void
			 */
			public function setId( $valor ) {
				$this->id = (string) $valor;
			}

			/**
			 * Id.
			 *
			 * @return string
			 */
			public function getId() {
				return $this->id;
			}

			/**
			 * Enlace al evento.
			 *
			 * @param string $valor URL.
			 * @return void
			 */
			public function setHtmlLink( $valor ) {
				$this->html_link = (string) $valor;
			}

			/**
			 * Enlace al evento.
			 *
			 * @return string
			 */
			public function getHtmlLink() {
				return $this->html_link;
			}
		}
	}
}

namespace Google\Service {

	/**
	 * Colección de eventos.
	 */
	if ( ! class_exists( '\Google\Service\CalendarEvents', false ) ) {

		/**
		 * Eventos.
		 */
		class CalendarEvents {

			/**
			 * Crea un evento.
			 *
			 * @param string $calendar_id Calendario.
			 * @param Event  $evento      Evento.
			 * @return Event
			 *
			 * @throws \Exception Si la prueba pide que falle.
			 */
			public function insert( $calendar_id, $evento ) {
				$GLOBALS['__sdk_llamadas'][] = array( 'evento_crear', $calendar_id, $evento->getSummary() );

				return $this->responde( $evento );
			}

			/**
			 * Actualiza un evento.
			 *
			 * @param string $calendar_id Calendario.
			 * @param string $event_id    Evento.
			 * @param Event  $evento      Evento.
			 * @return Event
			 *
			 * @throws \Exception Si la prueba pide que falle.
			 */
			public function update( $calendar_id, $event_id, $evento ) {
				$GLOBALS['__sdk_llamadas'][] = array( 'evento_actualizar', $event_id );

				return $this->responde( $evento );
			}

			/**
			 * Borra un evento.
			 *
			 * @param string $calendar_id Calendario.
			 * @param string $event_id    Evento.
			 * @return void
			 *
			 * @throws \Exception Si la prueba pide que falle.
			 */
			public function delete( $calendar_id, $event_id ) {
				$GLOBALS['__sdk_llamadas'][] = array( 'evento_borrar', $calendar_id, $event_id );

				if ( ! empty( $GLOBALS['__sdk']['falla_borrar'] ) ) {
					throw new \Exception( 'la API dijo que no' );
				}
			}

			/**
			 * Respuesta simulada.
			 *
			 * @param Event $evento Evento.
			 * @return Event
			 *
			 * @throws \Exception Si la prueba pide que falle.
			 */
			private function responde( $evento ) {
				if ( ! empty( $GLOBALS['__sdk']['falla_evento'] ) ) {
					throw new \Exception( 'la API dijo que no' );
				}

				$evento->setId( $GLOBALS['__sdk']['evento_id'] ?? 'EV-1' );
				$evento->setHtmlLink( $GLOBALS['__sdk']['evento_link'] ?? 'https://calendar.example.test/e/1' );

				return $evento;
			}
		}
	}

	/**
	 * Servicio de Calendar.
	 */
	if ( ! class_exists( '\Google\Service\Calendar', false ) ) {

		/**
		 * Servicio.
		 */
		class Calendar {

			/**
			 * Eventos.
			 *
			 * @var CalendarEvents
			 */
			public $events;

			/**
			 * Constructor.
			 *
			 * @param object|null $client Cliente.
			 */
			public function __construct( $client = null ) {
				unset( $client );
				$this->events = new CalendarEvents();
			}
		}
	}
}
