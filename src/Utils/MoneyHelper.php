<?php
/**
 * MoneyHelper - Utility per gestione precisa importi monetari
 * 
 * Questa classe gestisce gli importi monetari usando interi (centesimi)
 * per evitare errori di arrotondamento tipici dei float IEEE 754.
 * 
 * Convenzione: tutti gli importi sono espressi in centesimi (1 EUR = 100 centesimi)
 * 
 * Riferimento: Issue #2 - PROBLEMS.md
 * https://github.com/AlexMaina05/RICEVUTE/issues/2
 */
class MoneyHelper
{
    /**
     * Converte un importo in EUR (decimal) in centesimi (integer)
     * 
     * @param float|string $amount Importo in EUR (es. 12.50)
     * @return int Importo in centesimi (es. 1250)
     * 
     * Esempio:
     *   MoneyHelper::toCents(12.50) => 1250
     *   MoneyHelper::toCents("12.50") => 1250
     */
    public static function toCents($amount): int
    {
        // Usa bcmath per precisione quando disponibile, altrimenti fallback su cast
        if (function_exists('bcmul')) {
            // Converti a stringa e moltiplica per 100 con precisione
            $cents = bcmul((string)$amount, '100', 0);
            return (int)$cents;
        }
        
        // Fallback: arrotonda per evitare problemi con float
        return (int)round((float)$amount * 100);
    }

    /**
     * Converte centesimi (integer) in EUR (string decimal)
     * 
     * @param int $cents Importo in centesimi (es. 1250)
     * @return string Importo in EUR formattato (es. "12.50")
     * 
     * Esempio:
     *   MoneyHelper::toDecimal(1250) => "12.50"
     *   MoneyHelper::toDecimal(1005) => "10.05"
     */
    public static function toDecimal(int $cents): string
    {
        if (function_exists('bcdiv')) {
            return bcdiv((string)$cents, '100', 2);
        }
        
        return number_format($cents / 100, 2, '.', '');
    }

    /**
     * Somma importi in centesimi
     * 
     * @param int ...$amounts Importi variabili in centesimi
     * @return int Somma in centesimi
     * 
     * Esempio:
     *   MoneyHelper::add(1250, 500, 320) => 2070
     */
    public static function add(int ...$amounts): int
    {
        return array_sum($amounts);
    }

    /**
     * Sottrae importi in centesimi
     * 
     * @param int $amount Importo base in centesimi
     * @param int ...$subtract Importi da sottrarre in centesimi
     * @return int Risultato in centesimi
     * 
     * Esempio:
     *   MoneyHelper::subtract(2000, 500, 250) => 1250
     */
    public static function subtract(int $amount, int ...$subtract): int
    {
        return $amount - array_sum($subtract);
    }

    /**
     * Moltiplica un importo per una quantità
     * 
     * @param int $cents Importo unitario in centesimi
     * @param int $quantity Quantità (numero intero)
     * @return int Risultato in centesimi
     * 
     * Esempio:
     *   MoneyHelper::multiply(1250, 3) => 3750
     */
    public static function multiply(int $cents, int $quantity): int
    {
        return $cents * $quantity;
    }

    /**
     * Formatta un importo in centesimi per visualizzazione
     * 
     * @param int $cents Importo in centesimi
     * @param string $currency Simbolo valuta (default: EUR)
     * @return string Stringa formattata (es. "12.50 EUR")
     * 
     * Esempio:
     *   MoneyHelper::format(1250) => "12.50 EUR"
     *   MoneyHelper::format(1250, "€") => "12.50 €"
     */
    public static function format(int $cents, string $currency = 'EUR'): string
    {
        return self::toDecimal($cents) . ' ' . $currency;
    }

    /**
     * Valida che un importo in centesimi sia nel range accettabile
     * 
     * @param int $cents Importo in centesimi
     * @param int $min Minimo in centesimi (default: 0)
     * @param int $max Massimo in centesimi (default: 99999999 = €999,999.99)
     * @return bool True se valido
     * 
     * Esempio:
     *   MoneyHelper::isValidAmount(1250, 0, 100000) => true
     *   MoneyHelper::isValidAmount(-100) => false
     */
    public static function isValidAmount(int $cents, int $min = 0, int $max = 99999999): bool
    {
        return $cents >= $min && $cents <= $max;
    }

    /**
     * Confronta due importi
     * 
     * @param int $cents1 Primo importo in centesimi
     * @param int $cents2 Secondo importo in centesimi
     * @return int -1 se cents1 < cents2, 0 se uguali, 1 se cents1 > cents2
     * 
     * Esempio:
     *   MoneyHelper::compare(1250, 1000) => 1
     *   MoneyHelper::compare(1000, 1250) => -1
     *   MoneyHelper::compare(1250, 1250) => 0
     */
    public static function compare(int $cents1, int $cents2): int
    {
        if ($cents1 < $cents2) return -1;
        if ($cents1 > $cents2) return 1;
        return 0;
    }
}
?>
