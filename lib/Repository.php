<?php

require_once 'ConnectionHandler.php';

/**
 * Das Model ist das M in MVC. Es ist zustängig für alles, was mit der Datenbank
 * zu tun hat. Dazu gehört zum Beispiel:
 *   - Einzelner Datensatz aus der Datenbenk auslesen (SELECT...  WHERE id =)
 *   - Alle Datensätze, welche bestimmte Kriterien erfüllen aus der Datenbank
 *       auslesen. (SELECT mit WHERE)
 *   - Den Datensatz mit einer bestimmten id löschen
 *   - Einen neuen Datensatz erstellen.
 *   - Prüfen ob in der Benutzertabelle ein Datensatz mit dem vom Benutzer
 *       eingegebenen benutzernamen und passwort gibt.
 *
 * Die Idee ist hier, dass es für jede solche Operation auf dem Model eine
 * Funktion gibt. Diese Funktionen haben allenfalls Parameter (z.B. id des
 * auszulesenden Datensatzes) und im Normalfall auch einen Rückgabewert.
 * Rückgabe werte können sein:
 *   - Einzelner Datensatz (z.B. Datensatz bei id auslesen)
 *   - Array von Datensätzen (z.B. Suchresultate auslesen)
 *   - Boolscher wert, welcher z.B. sagt, ob ein Benutzer mit dem gegebenen
 *       Namen und Passwort existiert.
 *   - Kein Rückgebewert beim erstellen oder ändern eines Datensatzes
 *
 * -----------------------------------------------------------------------------
 * -- Implementation -----------------------------------------------------------
 * Für jede Tabelle in der Datenbank sollte es ein gleichnamiges Model geben.
 * Für die Tabelle user also eine Klasse UserModel (im Verzeichnis model),
 * welche von der Klasse Model erbt (diese hier). Eine minimale Model
 * implementation sieht folgendermassen aus:
 *
 *    <?php
 *    require_once 'lib/Model.php';
 *
 *    class UserModel extends Model
 *    {
 *      protected $tableName = 'user';
 *    }
 *
 * Die Vererbung macht daher sinn, dass in der Model Klasse Funktionen
 * implementiert werden, welche für alle Tabellen (fast) gleich sind. So müssen
 * diese nicht in jedem Model einzel implementiert werten. Ein Beispiel darür
 * ist das auslesen eines Datensatzes bei dessen id (siehe readById unten). So
 * könnte mit dem obigen Model bereits ein Bentzer bei dessen id ausgelesen
 * werden.
 *
 *   require_once 'model/UserModel.php'
 *
 *   [...]
 *
 *   $wantedId = $_GET['id'];
 *
 *   $model = new UserModel();
 *   $user = $model->readById($wantedId);
 *
 *   // User für die Darstellung der View übergeben
 *
 * Alle Funktionen, welche nich für alle Tabellen funktionieren (z.B. Datensatz
 * erstellen, da die Spalten immer unterschiedlich heissen), werden dann im
 * konkreten Model (z.B. UserModel) implementiert.
 *
 * Um eine Verbindung auf die Datenbank zu bekommen, hilft der
 * ConnectionHandler. Siehe dessen Information für genaueres.
 *
 * -----------------------------------------------------------------------------
 * -- Errorhandling ------------------------------------------------------------
 * Sollten bei der Interaktion mit der Datenbank Fehler auftreten, macht es
 * sinn, Exceptions zu werfen.
 *
 *   $statement = ...
 *   if (!$statement->execute()) {
 *     throw new Exception("Ein Fehler ist aufgetreten: $result->error");
 *   }
 */


class Repository
{
    /**
     * Damit die generischen Querys wisse, um welche Tabelle es sich handelt,
     * gibt es diese Variabel. Diese muss in den konkreten Implementationen mit
     * dem Tabellennamen überschrieben werden. (Siehe beispiel oben).
     */
    protected $tableName = null;

    /**
     * @param $select the attribute for a sql select
     * @param $table
     * @param $where for intance select bla from bla WHERE =
     * @param $isEqual the value after where
     * @param int $numberOfResults the number of "elements" in select
     * @return array
     * @throws Exception if an prepare or exicution erorr occured
     */

    protected function select($select, $table, $where, $isEqual, $numberOfResults = 1)
    {
        echo "<script> console.log('select'); </script>";
        $db = ConnectionHandler::getConnection();
        $query = "SELECT {$select} FROM {$table} WHERE {$where} = ?";

        //$query = "SELECT password from user WHERE name = 'kenan'";
        $stmt = $db->prepare($query);

        if ($stmt == false)
        {
            throw new Exception('Prepare Error');
        }
        else
        {
            //$stmt->bind_param('sss', $select, $where, $isEqual);
            $stmt->bind_param('s', $isEqual);


            if (!$stmt->execute())
            {
                throw new Exception('Exicution error');
            }

            //for query with muliple select attributes
            switch ($numberOfResults)
            {
                case 2:
                    $stmt->bind_result($result_column1, $result_column2);

                    $obj = array();
                    while($stmt->fetch())
                    {

                        $x = array (
                            $result_column1,
                            $result_column2
                        );

                        array_push($obj, $x);
                    }
                    break;
                case 3:
                    $stmt->bind_result($result_column1, $result_column2, $result_column3);

                    $obj = array();
                    while($stmt->fetch())
                    {


                        array_push($obj, $result_column1);
                        array_push($obj, $result_column2);
                        array_push($obj, $result_column3);
                    }
                    break;
                default:
                    $stmt->bind_result($result_column);

                    $obj = array();
                    while($stmt->fetch())
                    {
                        array_push($obj, $result_column);
                    }
                    break;
            }


            $stmt->close();


            //var_dump($obj);

            return $obj;
        }
    }

    //this method will use if it time
    protected function insert($tables, $attributes, $array)
    {

        $types = '';

        $query = "INSERT INTO {$tables} {$attributes} VALUES (";
        foreach ($array as $key => $item)
        {
            if ($key === 0)
                $query .= '?';
            $query .= ', ?';
            $types .= 's';
        }
        $query .= ')';

        $values = "$types, $query";

        $stmt = ConnectionHandler::getConnection()->prepare($query);

        if ($stmt == false)
        {
            $this->displayErrorPrepareStatement();
            return false;
        }
        else
        {
            $stmt->bind_param($types, $values);

            if (!$stmt->execute())
            {
                $this->displayErrorExicutionError();
                return true;
            }
            return true;
        }

    }

    public function update($table, $setName, $setValue, $whereName, $whereValue) {
        $stmt = ConnectionHandler::getConnection()->prepare("UPDATE {$table} SET ? = ? WHERE ? = ?");

        if ($stmt == false)
        {
            $this->displayErrorPrepareStatement();
            return false;
        }
        else
            {
                $stmt->bind_param('ssss', $setName, $setValue, $whereName, $whereValue);

                if (!$stmt->execute()) {
                    $this->displayErrorExicutionError();
                    return true;
                }
                return false;
            }
    }

    public function delete($table, $whereName, $whereValue)
    {
        $stmt = ConnectionHandler::getConnection()->prepare("DELETE FROM {$table} WHERE ? = ?");

        if ($stmt == false)
        {
            $this->displayErrorPrepareStatement();
            return false;
        }
        else
        {
            $stmt->bind_param('ss', $whereName, $whereValue);
            if (!$stmt->execute())
            {
                $this->displayErrorExicutionError();
                return false;
            }
            return true;
        }
    }

    private function displayErrorPrepareStatement()
    {
        echo "<script> console.log('statemen prepare error'); </script>";
    }

    private function displayErrorExicutionError()
    {
        echo "<script> console.log('statemen execution error'); </script>";
    }

}
