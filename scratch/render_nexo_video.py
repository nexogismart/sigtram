from pathlib import Path
import math, json, subprocess
import numpy as np
from PIL import Image, ImageDraw, ImageFilter, ImageChops
import imageio_ffmpeg

root=Path.cwd()
out=root/'output/nexo-background'
source=Image.open(out/'nexo-fondo-original.png').convert('RGB')
SW,SH=source.size
W,H,FPS,SECONDS=1600,900,30,24
N=FPS*SECONDS
nodes=[(36,78),(173,166),(197,290),(292,274),(182,392),(273,453),(230,509),(325,551),(115,633),(269,718),(397,800),(489,749),(549,835),(697,782),(874,844),(1041,825)]
pulse=[(305,422),(371,422),(383,402),(400,439),(427,301),(442,503),(461,405),(478,436),(490,421),(592,421)]
def glow_layer(points,color,radius=10):
    layer=Image.new('RGB',source.size)
    d=ImageDraw.Draw(layer)
    for x,y in points:
        d.ellipse((x-radius,y-radius,x+radius,y+radius),fill=color)
    return layer.filter(ImageFilter.GaussianBlur(radius*.65))
blue=[glow_layer(nodes[::2],(0,120,235),13),glow_layer(nodes[1::2],(0,145,245),13)]
line=Image.new('RGB',source.size)
ImageDraw.Draw(line).line(pulse,fill=(210,28,38),width=8,joint='curve')
red=line.filter(ImageFilter.GaussianBlur(10))
segments=[math.hypot(b[0]-a[0],b[1]-a[1]) for a,b in zip(pulse,pulse[1:])]
def pulse_position(fraction):
    distance=fraction*sum(segments)
    for a,b,length in zip(pulse,pulse[1:],segments):
        if distance<=length:
            f=distance/length
            return a[0]+(b[0]-a[0])*f,a[1]+(b[1]-a[1])*f
        distance-=length
    return pulse[-1]
def scaled(layer,amount):
    return layer.point([round(i*amount) for i in range(256)]*3)
def render(t):
    t=t%SECONDS
    scene=ImageChops.screen(source,scaled(red,.25+.4*(.5-.5*math.cos(2*math.pi*t/4))))
    for i,layer in enumerate(blue):
        cycle=(6,8)[i]
        amount=.12+.6*(.5-.5*math.cos(2*math.pi*t/cycle+i*1.8))
        scene=ImageChops.screen(scene,scaled(layer,amount))
    f=(t%4)/4
    fade=math.sin(math.pi*f)**2
    x,y=pulse_position(f)
    dot=Image.new('RGB',source.size)
    d=ImageDraw.Draw(dot)
    d.ellipse((x-8,y-8,x+8,y+8),fill=(round(255*fade),round(80*fade),round(95*fade)))
    dot=dot.filter(ImageFilter.GaussianBlur(4))
    d=ImageDraw.Draw(dot)
    d.ellipse((x-2,y-2,x+2,y+2),fill=tuple(round(v*fade) for v in (255,235,240)))
    scene=ImageChops.screen(scene,dot)
    # Periodic camera motion; overscan prevents exposed edges throughout the cycle.
    theta=2*math.pi*t/SECONDS
    zoom=1.04+.012*(1-math.cos(theta))
    scale=max(W/SW,H/SH)*zoom
    cx=SW/2+8*math.sin(theta)
    cy=SH/2+6*math.sin(2*theta)
    inv=1/scale
    return scene.transform((W,H),Image.Transform.AFFINE,(inv,0,cx-W*inv/2,0,inv,cy-H*inv/2),resample=Image.Resampling.BICUBIC)

# A virtual frame at t=24 must be identical to the frame at t=0.
a=np.asarray(render(0),dtype=np.int16)
b=np.asarray(render(SECONDS),dtype=np.int16)
assert np.array_equal(a,b),'The animation endpoint is discontinuous'
last=np.asarray(render(SECONDS-1/FPS),dtype=np.int16)
next_frame=np.asarray(render(1/FPS),dtype=np.int16)
report={'duration_seconds':SECONDS,'fps':FPS,'frames':N,'width':W,'height':H,'virtual_endpoint_max_difference':int(np.abs(a-b).max()),'seam_mean_difference':float(np.abs(a-last).mean()),'first_step_mean_difference':float(np.abs(a-next_frame).mean())}
video=out/'nexo-fondo-continuo.mp4'
cmd=[imageio_ffmpeg.get_ffmpeg_exe(),'-y','-f','rawvideo','-vcodec','rawvideo','-pix_fmt','rgb24','-s',f'{W}x{H}','-r',str(FPS),'-i','-','-an','-c:v','libx264','-preset','medium','-crf','19','-pix_fmt','yuv420p','-movflags','+faststart',str(video)]
with (out/'video-encoding.log').open('w') as log:
    proc=subprocess.Popen(cmd,stdin=subprocess.PIPE,stderr=log)
    try:
        for frame in range(N):
            proc.stdin.write(render(frame/FPS).tobytes())
            if frame%180==0: print(f'Rendered {frame}/{N} frames',flush=True)
        proc.stdin.close()
        code=proc.wait()
        if code: raise RuntimeError(f'Video encoder exit {code}')
    except BaseException:
        proc.kill()
        raise
report['bytes']=video.stat().st_size
(out/'video-validation.json').write_text(json.dumps(report,indent=2),encoding='utf-8')
(out/'vista-previa-video.html').write_text('''<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Nexo Smart — Video en bucle continuo</title><style>html,body{margin:0;height:100%;background:#071a30}video{display:block;width:100%;height:100%;object-fit:contain}</style><video src="nexo-fondo-continuo.mp4" poster="nexo-fondo-original.png" autoplay muted loop playsinline controls aria-label="Fondo Nexo Smart con animación continua"></video></html>''',encoding='utf-8')
print(json.dumps(report),flush=True)
