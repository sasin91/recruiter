<?php
/**
 * The reference sentences Slop_index embeds: generic filler that says
 * nothing about its subject ("slop"), and sentences that carry something a
 * reader can check or use ("substance"). An article sentence is placed by
 * which side its nearest references are on, so these are written to span
 * the shapes each side comes in, not the topics: the substance side mixes
 * figures, commands, causes, comparisons and first-hand findings from many
 * fields, so a sentence doesn't count as substance for being about code.
 *
 * Add a sentence to the side it belongs on when a real article is placed
 * wrongly; keep the two lists about the same length.
 */
class Slop_reference {

    public const SLOP = [
        "In today's fast-paced digital landscape, staying ahead of the curve is more important than ever.",
        "In the ever-evolving world of technology, change is the only constant.",
        "Let's dive in and explore what makes this so powerful.",
        "Buckle up, because this is going to be a game-changer.",
        "It's important to note that every situation is unique.",
        "It is worth noting that there are many factors to consider.",
        "In conclusion, embracing these strategies will unlock your full potential.",
        "This is a testament to the transformative power of innovation.",
        "Whether you're a seasoned professional or just starting out, this guide has something for everyone.",
        "By leveraging cutting-edge solutions, teams can streamline their workflows and boost productivity.",
        "Ultimately, the key to success lies in continuous learning and adaptation.",
        "This approach empowers developers to build robust, scalable and maintainable applications.",
        "The possibilities are truly endless.",
        "Navigating the complexities of modern development can feel overwhelming.",
        "Harnessing the power of AI opens up a world of exciting new opportunities.",
        "At the end of the day, it all comes down to finding the right balance.",
        "This seamless integration delivers a smooth and intuitive experience.",
        "Remember, the journey of a thousand miles begins with a single step.",
        "So, what are you waiting for? Start your journey today!",
        "Stay tuned for more insights, and happy coding!",
        "In this article, we will delve into the intricacies of this fascinating topic.",
        "Understanding these concepts is crucial for anyone looking to succeed.",
        "This revolutionary technology is reshaping industries across the globe.",
        "Best practices play a pivotal role in ensuring success.",
        "Let's take a closer look at why this matters more than ever.",
        "The future is bright, and the opportunities are limitless.",
        "It's not just a tool; it's a mindset.",
        "Mastering this skill will take your career to the next level.",
        "This rich tapestry of ideas fosters collaboration and drives innovation.",
        "By following these simple tips, you can achieve remarkable results.",
        "Feel free to share your thoughts in the comments below!",
        "As we move forward, it is essential to stay curious and keep exploring.",
        "This holistic approach ensures that all stakeholders are aligned.",
        "Moreover, it plays a crucial role in shaping the landscape of modern software.",
        "Overall, this is an exciting time to be part of the industry.",
        "Here are some key takeaways to keep in mind.",
        "With great power comes great responsibility.",
        "The benefits are numerous and far-reaching.",
        "I hope this article has provided you with valuable insights.",
        "Embrace the change and watch your productivity soar.",
    ];

    public const SUBSTANCE = [
        "Across 400 runs, the median latency dropped from 220 ms to 41 ms after we added the index.",
        "Run git bisect start, mark the current commit bad and the last release good.",
        "The query scanned 3.2 million rows because the composite index started with the wrong column.",
        "We measured 18% fewer support tickets in the six weeks after the change.",
        "Set max_connections to 200 and restart the server; the default of 151 ran out at peak.",
        "The bug only appears when two requests write the same row within one millisecond.",
        "Version 2.4 renamed the option, so configs written for 2.3 silently fall back to the default.",
        "On a 4-core machine the build took 7 minutes; with the cache it takes 50 seconds.",
        "The trial enrolled 1,240 patients and followed them for three years.",
        "Each target in the Makefile lists its prerequisites, and make rebuilds only what changed.",
        "We tried Redis first, but its memory use grew to 9 GB, so we moved the queue to Postgres.",
        "The function returns null when the key is missing, which the caller never checked.",
        "Prices rose 4.1% in March, mostly from energy, while food prices fell.",
        "The circuit breaker opens after five failures in thirty seconds and retries after a minute.",
        "Our test suite caught the regression: the parser dropped the last line when the file had no newline.",
        "The RFC 822 header block ends at the first empty line, and the body follows.",
        "In the benchmark, compliance rose from 0.8% to 98.3% when the rule was written as a state machine.",
        "Install the package with pip install requests==2.31 and pin it in requirements.txt.",
        "The 2019 survey asked 3,000 developers and found that 61% deploy at least weekly.",
        "Replacing the nested loop with a hash map turned the O(n²) step into O(n).",
        "The bridge's main span is 1,624 metres and it opened to traffic in 1998.",
        "When the disk fills, the database switches to read-only mode instead of crashing.",
        "We rolled back after the error rate on checkout reached 3% within ten minutes.",
        "The grammar is EBNF: an expression is a term followed by zero or more plus or minus terms.",
        "The model was trained on 2 trillion tokens with a context length of 8,192.",
        "I interviewed twelve warehouse workers; nine said the scanner app was slower than paper.",
        "Two-phase commit blocks if the coordinator fails after the prepare phase.",
        "The API allows 60 requests per minute per key and answers 429 beyond that.",
        "Our first attempt failed because the timezone was stored as local time, not UTC.",
        "The patch adds a lock around the counter, which removes the race but costs about 5% throughput.",
        "The study compared the two diets over 12 weeks and found no difference in weight loss.",
        "Add the flag --no-cache to the docker build command to force a fresh layer.",
        "The cache hit rate was 92%, so the remaining misses dominated response time.",
        "Unlike SQLite, MySQL compares strings case-insensitively under the default collation.",
        "Copenhagen's metro runs every two to four minutes and carries about 120 million passengers a year.",
        "The fix is one line: close the file handle before the rename, because Windows locks open files.",
        "The paper reports an F1 score of 0.87 on the test set and 0.71 on out-of-domain text.",
        "Store the password hash with bcrypt at cost 12; it takes about 250 ms to verify.",
        "Each init script under /etc/init.d answers start, stop and status.",
        "We cut the image from 1.2 GB to 180 MB by building in one stage and copying only the binary.",
    ];
}
