package DaemonService;
use strict;
use warnings;
use Time::HiRes qw(sleep);

print "Simulating heavy Perl startup (takes 2 seconds)...\n";
sleep(2.0);
print "Perl module initialized.\n";

sub generate {
    my $prompt = shift;
    return "Perl AI says: Hello! You asked: $prompt";
}

1;
