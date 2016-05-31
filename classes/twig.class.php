<?php 

// vi hämtar läser in den för Twig nödvändiga filen autoloader.php med hjälp av require_once.
// får vi inget felmeddelande någonstans om denna vet vi att det blivit rätt
require_once('Twig/lib/Twig/Autoloader.php');

// vi anger klassens namn
class Twig {

    // dessa två variabler är de enda som kommer användas av vårt twig-objekt
    private $data, $twig;

    // när vi konstruerar vårt twig-objekt skickar vi med vår data in
    // men vi behöver egentligen inte skicka med någon data om vi inte vill, vi har sagt åt metoden att
    // om det saknas en $data-variabel så ska den automatiskt göra om den till en tom array ($data = [])
    // anger vi igen specifik sökväg kommer den sedan leta efter .twig-filen i mappen /templates/
    // men vi kan lika gärna ange en egen mapp om vi vill det
    function __construct($data = [], $templateSource = 'templates/') {

        // när vi skapar ett twig-objekt kör vi några av twig förinställda rader
        Twig_Autoloader::register();
        $loader = new Twig_Loader_Filesystem($templateSource);
        $this->twig = new Twig_Environment($loader);

        // den $data-variabel som vi skickat med in i objektet lagrar vi i objektets egna data-variabel ($this->data)
        // då följer den alltid med objektet
        $this->data = $data;

    }

    // om vi skulle vilja ändra datan vi skickar med i variabeln, eller lägga till den efter att vi skapat objektet
    // kan vi göra det med metoden addData($data)
    // då byts $this->data ut mot det vi skickar med in i metoden
    function addData($data = false) {
        $this->data = $data;
    }
    
    // render är metoden för att ta datan och applicera den på en .html-fil, .twig-fil eller vad vi väljer att kalla den. filen med HTML-struktur hur som helst.
    function render($target){
        return $this->twig->render($target, $this->data);
    }

}
