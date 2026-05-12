export type Die = { value: number; held: boolean };

export type YahtzeeCategory =
    | 'ones'
    | 'twos'
    | 'threes'
    | 'fours'
    | 'fives'
    | 'sixes'
    | 'three_of_a_kind'
    | 'four_of_a_kind'
    | 'full_house'
    | 'small_straight'
    | 'large_straight'
    | 'yahtzee'
    | 'chance';

export type Scorecard = {
    [K in YahtzeeCategory]: number | null;
} & {
    yahtzee_bonus: number;
};

export type PlayerSummary = { id: number; name: string };

export type YahtzeeGame = {
    id: number;
    user_id: number;
    player_one_id: number;
    player_two_id: number;
    current_turn_user_id: number;
    dice: Die[];
    rolls_left: number;
    scorecards: Record<number, Scorecard>;
    status: 'active' | 'completed';
    winner_id: number | null;
    player_one: PlayerSummary;
    player_two: PlayerSummary;
    current_turn_user: PlayerSummary;
    winner: PlayerSummary | null;
    created_at: string;
    updated_at: string;
};

export type Totals = {
    upper: number;
    upper_bonus: number;
    lower: number;
    yahtzee_bonus: number;
    grand: number;
};

export const UPPER_CATEGORIES: YahtzeeCategory[] = [
    'ones', 'twos', 'threes', 'fours', 'fives', 'sixes',
];

export const LOWER_CATEGORIES: YahtzeeCategory[] = [
    'three_of_a_kind', 'four_of_a_kind', 'full_house',
    'small_straight', 'large_straight', 'yahtzee', 'chance',
];

export const CATEGORY_LABELS: Record<YahtzeeCategory, string> = {
    ones: 'Ones',
    twos: 'Twos',
    threes: 'Threes',
    fours: 'Fours',
    fives: 'Fives',
    sixes: 'Sixes',
    three_of_a_kind: 'Three of a Kind',
    four_of_a_kind: 'Four of a Kind',
    full_house: 'Full House',
    small_straight: 'Small Straight',
    large_straight: 'Large Straight',
    yahtzee: 'Yahtzee',
    chance: 'Chance',
};
