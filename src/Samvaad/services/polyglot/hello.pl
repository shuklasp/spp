#!/usr/bin/perl
use strict;
use warnings;

my $input = $ARGV[0] || '';

if (!$input) {
    print '{"error": "No arguments provided"}';
    exit;
}

my $name = "Unknown";
if ($input =~ /"name"\s*:\s*"([^"]+)"/) {
    $name = $1;
}

print "{\n";
print qq(  "status": "success",\n);
print qq(  "lang": "Perl",\n);
print qq(  "greeting": "Hello $name from Perl!",\n);
print qq(  "received_data": $input\n);
print "}\n";
