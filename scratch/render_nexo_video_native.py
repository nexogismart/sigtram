from pathlib import Path
import subprocess,math,json
import imageio_ffmpeg
p=Path('output/nexo-background')
ff=imageio_ffmpeg.get_ffmpeg_exe()
# 720 distinct frames sample one closed periodic trajectory without duplicating the endpoint.
# All expressions use integer frame numbers to avoid accumulated timing drift.
t='(2*PI*on/720)'
zoom=f'1.055-0.015*cos({t})'
x=f'iw/2-iw/zoom/2+6*sin({t})'
y=f'ih/2-ih/zoom/2+4*sin(2*{t})'
vf=f"scale=1600:900,zoompan=z='{zoom}':x='{x}':y='{y}':d=720:s=1600x900:fps=30,eq=gamma_r='1+0.035*(1-cos(2*PI*t/4))':gamma_b='1+0.018*(1-cos(2*PI*t/8))':eval=frame,format=yuv420p"
cmd=[ff,'-y','-threads','2','-i',str(p/'nexo-fondo-original.png'),'-vf',vf,'-frames:v','720','-an','-c:v','libx264','-threads','2','-preset','fast','-crf','19','-movflags','+faststart',str(p/'nexo-fondo-continuo-v2.mp4')]
with (p/'video-encoding-v2.log').open('w') as log:
    proc=subprocess.run(cmd,stdout=log,stderr=log)
if proc.returncode:raise RuntimeError('Video encoding failed')
(p/'vista-previa-video.html').write_text('''<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Nexo Smart — Video en bucle continuo</title><style>html,body{margin:0;height:100%;background:#071a30}video{display:block;width:100%;height:100%;object-fit:contain}</style><video src="nexo-fondo-continuo-v2.mp4" poster="nexo-fondo-original.png" autoplay muted loop playsinline controls aria-label="Fondo Nexo Smart con animación continua"></video></html>''',encoding='utf-8')
print(json.dumps({'video':str(p/'nexo-fondo-continuo-v2.mp4'),'duration':24,'fps':30,'size':[1600,900],'bytes':(p/'nexo-fondo-continuo-v2.mp4').stat().st_size}),flush=True)
