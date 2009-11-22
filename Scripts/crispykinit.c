// crispykinit.c
// A rudimentary kinit that reads a password from stdin, meant for use by scripts
// It stores the credentials cache in a separate, given directory (see below)
// Compile with: gcc crispykinit.c -lkrb5 -lcom_err -o crispykinit

#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <krb5.h>
#include <com_err.h>

// Change this to the directory where you want user credentials caches to be stored
#define CCDIR "/tmp/webacctshell/ccache"

char *getccachename(char *user) {
        int i, l;
        static char namebuf[2048];
        char *namecpy = strdup(user);
        l = strlen(namecpy);
        for(i = 0; i < l; i++) {
                if(namecpy[i] == '@') namecpy[i] = 0;
                if(namecpy[i] == '/') namecpy[i] = '@';
        }
        snprintf(namebuf, 2048, "FILE:%s/%s", CCDIR, namecpy);
        free(namecpy);
        return namebuf;
}

int storetgt(krb5_context ctx, char *princstr, char *pass) {
        krb5_creds tgtcreds;
        krb5_principal princ;
        krb5_get_init_creds_opt *options = NULL;
        krb5_ccache cc;
        krb5_error_code kerr;
        //kerr = krb5_init_context(&ctx);
        //if(kerr) return kerr;
        memset(&tgtcreds, 0, sizeof(tgtcreds));
        kerr = krb5_parse_name(ctx, princstr, &princ);
        if(kerr) { return kerr; }
        kerr = krb5_get_init_creds_opt_alloc(ctx, &options);
        if(kerr) { krb5_free_principal(ctx, princ); return kerr; }
        krb5_get_init_creds_opt_set_forwardable(options, 1);
        krb5_get_init_creds_opt_set_address_list(options, NULL);
        kerr = krb5_get_init_creds_password(ctx, &tgtcreds, princ, pass, NULL, NULL, 0, NULL, options);
        krb5_get_init_creds_opt_free(ctx, options);
        if(kerr) { krb5_free_principal(ctx, princ); return kerr; }
        kerr = krb5_cc_resolve(ctx, getccachename(princstr), &cc);
        if(kerr) { krb5_free_cred_contents(ctx, &tgtcreds); krb5_free_principal(ctx, princ); return kerr; }
        kerr = krb5_cc_initialize(ctx, cc, princ);
        if(kerr) { krb5_free_cred_contents(ctx, &tgtcreds); krb5_free_principal(ctx, princ); krb5_cc_close(ctx, cc); return kerr; }
        kerr = krb5_cc_store_cred(ctx, cc, &tgtcreds);
        if(kerr) { krb5_free_cred_contents(ctx, &tgtcreds); krb5_free_principal(ctx, princ); krb5_cc_close(ctx, cc); return kerr; }
        krb5_free_cred_contents(ctx, &tgtcreds);
        krb5_free_principal(ctx, princ);
        krb5_cc_close(ctx, cc);
        return 0;
}

int main(int argc, char **argv) {
        int r;
        krb5_context ctx;
        char passbuf[1025];
        char *fr;
        int i;
        if(argc != 2) {
                printf("Usage: %s <Principal>\n", argv[0]);
        }
        memset(passbuf, 0, 1025);
        fr = fgets(passbuf, 1024, stdin);
        if(!fr) return 1;
        for(i = 0; i < 1025; i++) if(passbuf[i] == '\n') passbuf[i] = 0;
        krb5_init_context(&ctx);
        r = storetgt(ctx, argv[1], passbuf);
        if(r) {
                com_err("crispykinit", r, "");
                return 1;
        }
	 printf("1");
        return 0;
}