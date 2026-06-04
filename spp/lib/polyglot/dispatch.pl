use strict;
use warnings;
use JSON;
use File::Basename;
use IO::Socket::INET;
use lib dirname(__FILE__);
use Cwd qw(abs_path);

my $module_file = abs_path($ARGV[0]) || $ARGV[0];

# Deduce package name from file
my $pkg = "main";
if (open my $fh, "<", $module_file) {
    while (<$fh>) {
        if (/^\s*package\s+([A-Za-z0-9_:]+)\s*;/) {
            $pkg = $1;
            last;
        }
    }
    close $fh;
}

if (grep { $_ eq "--daemon" } @ARGV) {
    my $port_file = "";
    for (my $i = 0; $i < @ARGV; $i++) {
        if ($ARGV[$i] eq "--daemon") { $port_file = $ARGV[$i+1]; last; }
    }
    
    eval "require '$module_file'";
    if ($@) { die $@; }
    
    my $server = IO::Socket::INET->new(LocalHost => "127.0.0.1", LocalPort => 0, Proto => "tcp", Listen => 5, Reuse => 1) or die "Cannot create socket: $!";
    open(my $fh, ">", $port_file) or die "Cannot write to $port_file: $!";
    print $fh $server->sockport();
    close($fh);
    
    while (my $client = $server->accept()) {
        my $data = "";
        while (<$client>) {
            $data .= $_;
            last if /\n/;
        }
        next unless $data;
        my $req = eval { decode_json($data) };
        if (!$@ && $req) {
            my $func = $req->{func};
            my $args = $req->{args} || [];
            my $result;
            if (ref($args) eq "ARRAY") {
                no strict "refs";
                $result = eval { &{"${pkg}::${func}"}(@$args) };
            } else {
                no strict "refs";
                $result = eval { &{"${pkg}::${func}"}($args) };
            }
            print $client encode_json($result) . "\n" if !$@;
        }
        close($client);
    }
} else {
    my $func = $ARGV[1];
    my $args_raw = do { local $/; <STDIN> };
    my $args = $args_raw ? decode_json($args_raw) : [];
    eval "require '$module_file'";
    if ($@) { die $@; }
    my $result;
    if (ref($args) eq "ARRAY") {
        no strict "refs";
        $result = &{"${pkg}::${func}"}(@$args);
    } else {
        no strict "refs";
        $result = &{"${pkg}::${func}"}($args);
    }
    print encode_json($result);
}