<?php

namespace Database\Factories\Support;

use Faker\Provider\Base;

/**
 * Fictional Bangladeshi names, phone numbers and Dhaka addresses for factories
 * and demo data, so screens look realistic to clinic staff during testing.
 *
 *   fake_bd()->name('female')   // "Nusrat Jahan"
 *   fake_bd()->phone()           // "01712345678"
 *   fake_bd()->address()         // "House 12, Road 5, Dhanmondi, Dhaka"
 *
 * Every combination is made up. Never put real patient data in factories or seeders.
 * Uses the shared fake() generator, so fake()->seed() makes results repeatable.
 */
class BangladeshProvider extends Base
{
    /** @var list<string> */
    protected static array $maleFirst = [
        'Rahim', 'Karim', 'Hasan', 'Mahmud', 'Arif', 'Sakib', 'Tanvir', 'Imran', 'Nayeem', 'Rafiq',
        'Jamal', 'Kamal', 'Habib', 'Sohel', 'Rubel', 'Fahim', 'Asif', 'Mehedi', 'Rakib', 'Shahin',
        'Faisal', 'Zahid', 'Masud', 'Anik', 'Pritom', 'Sourav', 'Tamim', 'Mizanur', 'Shakil', 'Ridwan',
    ];

    /** @var list<string> */
    protected static array $femaleFirst = [
        'Fatema', 'Ayesha', 'Nusrat', 'Sharmin', 'Tahmina', 'Rumana', 'Farzana', 'Sadia', 'Jannat', 'Mim',
        'Shirin', 'Nasrin', 'Rokeya', 'Taslima', 'Sumaiya', 'Afroza', 'Lamia', 'Tania', 'Ruma', 'Shapla',
        'Puja', 'Moumita', 'Rupa', 'Mahmuda', 'Israt', 'Sabrina', 'Nadia', 'Anika', 'Maliha', 'Tasnim',
    ];

    /** @var list<string> */
    protected static array $familyNames = [
        'Rahman', 'Hossain', 'Islam', 'Ahmed', 'Chowdhury', 'Khan', 'Uddin', 'Sarker', 'Das', 'Saha',
        'Roy', 'Talukder', 'Mia', 'Siddique', 'Haque', 'Karim', 'Alam', 'Biswas', 'Bhuiyan', 'Molla',
    ];

    /** @var list<string> */
    protected static array $femaleOnlyFamilyNames = ['Akter', 'Begum', 'Jahan', 'Khatun', 'Sultana'];

    /** @var list<string> */
    protected static array $areas = [
        'Dhanmondi', 'Mirpur', 'Uttara', 'Mohammadpur', 'Gulshan', 'Banani', 'Bashundhara', 'Badda',
        'Rampura', 'Motijheel', 'Lalbagh', 'Jatrabari', 'Tejgaon', 'Khilgaon', 'Shyamoli', 'Malibagh',
    ];

    /** Mobile operator prefixes in use in Bangladesh. */
    protected static array $mobilePrefixes = ['013', '014', '015', '016', '017', '018', '019'];

    /** @param  'male'|'female'|null  $gender */
    public function firstName(?string $gender = null): string
    {
        $gender ??= static::randomElement(['male', 'female']);

        return static::randomElement($gender === 'female' ? static::$femaleFirst : static::$maleFirst);
    }

    /** @param  'male'|'female'|null  $gender */
    public function name(?string $gender = null): string
    {
        $gender ??= static::randomElement(['male', 'female']);
        $family = $gender === 'female'
            ? static::randomElement([...static::$familyNames, ...static::$femaleOnlyFamilyNames])
            : static::randomElement(static::$familyNames);

        return $this->firstName($gender).' '.$family;
    }

    /** An 11-digit mobile number, e.g. 01712345678. */
    public function phone(): string
    {
        return static::randomElement(static::$mobilePrefixes).static::numerify('########');
    }

    public function area(): string
    {
        return static::randomElement(static::$areas);
    }

    public function address(): string
    {
        return sprintf('House %d, Road %d, %s, Dhaka', static::numberBetween(1, 120), static::numberBetween(1, 30), $this->area());
    }
}
