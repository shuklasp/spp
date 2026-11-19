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
    my $auth_token = "";
    my $pid_file = "";
    for (my $i = 0; $i < @ARGV; $i++) {
        if ($ARGV[$i] eq "--daemon") { $port_file = $ARGV[$i+1]; }
        if ($ARGV[$i] eq "--auth-token") { $auth_token = $ARGV[$i+1]; }
        if ($ARGV[$i] eq "--pid-file") { $pid_file = $ARGV[$i+1]; }
    }
    
    eval "require '$module_file'";
    if ($@) { die $@; }
    
    my $server = IO::Socket::INET->new(
        LocalHost => "127.0.0.1",
        LocalPort => 0,
        Proto => "tcp",
        Listen => 16,
        Reuse => 1
    ) or die "Cannot create socket: $!";

    my $port = $server->sockport();
    my $tmp_port = $port_file . ".tmp." . $$;
    open(my $pfh, ">", $tmp_port) or die "Cannot write to $tmp_port: $!";
    print $pfh $port;
    close($pfh);
    rename($tmp_port, $port_file);

    if ($pid_file) {
        my $tmp_pid = $pid_file . ".tmp." . $$;
        open(my $pidfh, ">", $tmp_pid);
        print $pidfh $$;
        close($pidfh);
        rename($tmp_pid, $pid_file);
    }
    
    while (my $client = $server->accept()) {
        my $data = "";
        while (<$client>) {
            $data .= $_;
            last if /\n/;
            if (length($data) > 10 * 1024 * 1024) { last; }
        }
        next unless $data;
        my $req = eval { decode_json($data) };
        if ($@ || !$req) {
            print $client encode_json({ success => JSON::false, error => "Invalid JSON payload" }) . "\n";
            close($client);
            next;
        }

        my $req_id = $req->{request_id} || "";

        if ($auth_token && ($req->{auth_token} || "") ne $auth_token) {
            print $client encode_json({ success => JSON::false, error => "Unauthorized: invalid auth_token", error_code => 401, request_id => $req_id }) . "\n";
            close($client);
            next;
        }

        my $func = $req->{func} || $req->{action} || "";
        if ($func eq "__ping__" || $func eq "ping") {
            print $client encode_json({ success => JSON::true, data => "PONG", status => "ready", pid => $$, request_id => $req_id }) . "\n";
            close($client);
            next;
        }
        if ($func eq "__shutdown__" || $func eq "shutdown") {
            print $client encode_json({ success => JSON::true, message => "Worker shutting down", request_id => $req_id }) . "\n";
            close($client);
            last;
        }

        if ($func !~ /^[a-zA-Z0-9_]+$/) {
            print $client encode_json({ success => JSON::false, error => "Invalid function identifier", error_code => 400, request_id => $req_id }) . "\n";
            close($client);
            next;
        }

        my $args = $req->{args} || [];
        my $result;
        my $eval_err = "";
        {
            no strict "refs";
            if (ref($args) eq "ARRAY") {
                $result = eval { &{"${pkg}::${func}"}(@$args) };
            } else {
                $result = eval { &{"${pkg}::${func}"}($args) };
            }
            $eval_err = $@;
        }

        if ($eval_err) {
            print $client encode_json({ success => JSON::false, error => $eval_err, request_id => $req_id }) . "\n";
        } else {
            print $client encode_json({ success => JSON::true, data => $result, request_id => $req_id }) . "\n";
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
