<?php

namespace App\Services;

/** Rules for the two-player grid games (tic-tac-toe, Connect Four). */
class GridGameRules
{
    public const KINDS = [
        'tictactoe' => ['w' => 3, 'h' => 3, 'n' => 3],
        'connect4' => ['w' => 7, 'h' => 6, 'n' => 4],
    ];

    /** @return list<null> */
    public static function emptyBoard(string $kind): array
    {
        return array_fill(0, self::KINDS[$kind]['w'] * self::KINDS[$kind]['h'], null);
    }

    /**
     * Apply a move: a cell index for tic-tac-toe, a column for Connect Four (the disc drops).
     *
     * @param  list<int|null>  $board
     * @return list<int|null>|null the new board, or null if the move is illegal
     */
    public static function applyMove(string $kind, array $board, int $move, int $mark): ?array
    {
        ['w' => $w, 'h' => $h] = self::KINDS[$kind];

        if ($kind === 'connect4') {
            if ($move < 0 || $move >= $w) {
                return null;
            }
            for ($row = $h - 1; $row >= 0; $row--) {
                if ($board[$row * $w + $move] === null) {
                    $board[$row * $w + $move] = $mark;

                    return $board;
                }
            }

            return null;
        }

        if ($move < 0 || $move >= $w * $h || $board[$move] !== null) {
            return null;
        }
        $board[$move] = $mark;

        return $board;
    }

    /**
     * Winning mark (1 or 2), or null.
     *
     * @param  list<int|null>  $board
     */
    public static function winner(string $kind, array $board): ?int
    {
        ['w' => $w, 'h' => $h, 'n' => $n] = self::KINDS[$kind];

        /** @var list<array{int, int}> $directions */
        $directions = [[1, 0], [0, 1], [1, 1], [1, -1]];
        foreach ($directions as [$dx, $dy]) {
            for ($y = 0; $y < $h; $y++) {
                for ($x = 0; $x < $w; $x++) {
                    $mark = $board[$y * $w + $x];
                    if ($mark === null) {
                        continue;
                    }
                    for ($k = 1; $k < $n; $k++) {
                        $nx = $x + $dx * $k;
                        $ny = $y + $dy * $k;
                        if ($nx < 0 || $nx >= $w || $ny < 0 || $ny >= $h || $board[$ny * $w + $nx] !== $mark) {
                            continue 2;
                        }
                    }

                    return $mark;
                }
            }
        }

        return null;
    }

    /** @param  list<int|null>  $board */
    public static function isFull(array $board): bool
    {
        return ! in_array(null, $board, true);
    }
}
