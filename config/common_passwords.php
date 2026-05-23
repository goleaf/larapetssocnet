<?php

declare(strict_types=1);

$basePasswords = [
    '123456', '123456789', '12345', 'qwerty', 'password', '12345678', '111111', '123123', '1234567890',
    '1234567', 'qwerty123', '000000', '1q2w3e', 'aa12345678', 'abc123', 'password1', '1234', 'qwertyuiop',
    '123321', 'password123', '1q2w3e4r5t', 'iloveyou', '654321', '666666', '987654321', '123', '123456a',
    'qwe123', '1q2w3e4r', '7777777', '1qaz2wsx', '123qwe', 'zxcvbnm', '121212', 'asdasd', 'a123456',
    '555555', 'dragon', '112233', '123123123', 'monkey', '11111111', 'qazwsx', '159753', 'asdfghjkl',
    '222222', '1234qwer', 'qwerty1', '123654', '123abc', 'abc123456', 'qweasdzxc', 'football', 'baseball',
    'superman', 'batman', 'welcome', 'letmein', 'admin', 'login', 'princess', 'sunshine', 'master', 'shadow',
    'trustno1', 'passw0rd', 'whatever', 'hello', 'freedom', 'starwars', 'solo', 'flower', 'hottie', 'loveme',
    'zaq1zaq1', 'password!', 'password@123', 'p@ssw0rd', 'p@ssword', 'qwerty12', 'qwerty12345', 'qwerty2024',
    'qwerty2025', 'qwerty2026', 'asdf1234', 'asdfasdf', 'iloveu', 'lovely', 'killer', 'buster', 'jordan23',
    'michael', 'charlie', 'thomas', 'jessica', 'michelle', 'daniel', 'hunter', 'mustang', 'pepper', 'ginger',
    'cookie', 'summer', 'winter', 'spring', 'autumn', 'orange', 'purple', 'yellow', 'banana', 'pokemon',
    'naruto', 'matrix', 'internet', 'computer', 'secret', 'default', 'changeme', 'administrator', 'root',
    'toor', 'guest', 'user', 'test', 'demo', 'access', 'pass', 'passwd', 'letmein123', 'welcome1',
    'welcome123', 'admin123', 'administrator1', 'q1w2e3r4', 'q1w2e3r4t5', '1qazxsw2', 'zaq12wsx', 'abcd1234',
    'abc@123', 'abcd@1234', 'aaaaaa', 'abcabc', 'qqqqqq', 'zzzzzz', 'asdfgh', 'qwertyui', 'poiuytrewq',
    'mnbvcxz', '1qaz@wsx', 'pass1234', 'password12', 'password1234', 'password2024', 'password2025',
    'password2026', 'social', 'petsocial', 'larapets', 'petlover', 'doglover', 'catlover', 'puppy', 'kitten',
    'rabbit', 'hamster', 'golden', 'labrador', 'bulldog', 'beagle', 'poodle', 'snoopy', 'garfield',
];

$commonWords = [
    'password', 'qwerty', 'welcome', 'admin', 'login', 'letmein', 'secret', 'dragon', 'monkey', 'football',
    'baseball', 'master', 'shadow', 'superman', 'batman', 'princess', 'sunshine', 'flower', 'summer', 'winter',
    'spring', 'autumn', 'purple', 'orange', 'yellow', 'banana', 'cookie', 'ginger', 'pepper', 'hunter',
    'mustang', 'charlie', 'michael', 'jordan', 'daniel', 'jessica', 'michelle', 'thomas', 'computer', 'internet',
    'pokemon', 'naruto', 'matrix', 'starwars', 'freedom', 'whatever', 'trustno', 'loveme', 'hottie', 'solo',
    'petlover', 'doglover', 'catlover', 'puppy', 'kitten', 'petsocial', 'larapets', 'social', 'profile',
    'account', 'member', 'friend', 'family', 'coffee', 'cheese', 'soccer', 'hockey', 'tennis', 'basketball',
];

$suffixes = [
    '1', '12', '123', '1234', '12345', '123456', '!', '!!', '@', '@123', '#', '2020', '2021', '2022', '2023',
    '2024', '2025', '2026', '01', '007', '777', '000',
];

$passwords = $basePasswords;
$uniquePasswords = [];

foreach ($commonWords as $word) {
    foreach ($suffixes as $suffix) {
        $passwords[] = $word.$suffix;
    }
}

foreach (range(0, 9999) as $number) {
    $passwords[] = str_repeat((string) ($number % 10), 6);
    $passwords[] = 'password'.str_pad((string) $number, 4, '0', STR_PAD_LEFT);

    $uniquePasswords = array_values(array_unique(array_filter(array_map(
        static fn (string $password): string => strtolower(trim($password)),
        $passwords,
    ))));

    if (count($uniquePasswords) >= 500) {
        break;
    }
}

return [
    'passwords' => array_slice($uniquePasswords, 0, 500),
];
